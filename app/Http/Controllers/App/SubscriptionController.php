<?php

namespace App\Http\Controllers\App;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\SubscriptionService;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions, private readonly TenantContext $tenant) {}

    public function index(PlanLimits $limits): View
    {
        $organizationId = (int) $this->tenant->id();

        return view('app.subscription.index', [
            'subscription' => OrganizationSubscription::query()->with('plan')->first(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('price_paise')->get(),
            'usage' => $limits->usage($organizationId),
            'limits' => collect(['doctors', 'staff', 'locations', 'messages_per_month'])->mapWithKeys(fn (string $key) => [$key => $limits->limit($organizationId, $key)]),
            'invoices' => PlatformInvoice::query()->latest('id')->limit(24)->get(),
        ]);
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        return $this->startCheckout($request, $this->tenant->organization());
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        try {
            $days = $this->subscriptions->applyTrialCoupon($this->tenant->organization(), $data['code']);
        } catch (BillingException $exception) {
            // Not a trial coupon: it may be a discount, which applies when a plan is chosen.
            $request->session()->put('applied_coupon', $data['code']);

            return back()->with('error', $exception->getMessage().' If it is a discount coupon, it will be tried when you choose a plan.');
        }

        return back()->with('success', "{$days} days were added to your trial.");
    }

    public function cancel(): RedirectResponse
    {
        try {
            $this->subscriptions->cancel($this->tenant->organization());
        } catch (RequestException) {
            return back()->with('error', 'Razorpay did not accept that. Please try again or contact support.');
        }

        return back()->with('success', 'Your subscription will end at the close of this billing period.');
    }

    public function invoicePdf(PlatformInvoice $platformInvoice): Response
    {
        $platformInvoice->load('plan');

        $pdf = Pdf::loadView('pdf.platform-invoice', ['invoice' => $platformInvoice, 'organization' => $this->tenant->organization()]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$platformInvoice->number.'.pdf"',
        ]);
    }

    /**
     * Outside the tenant group on purpose: an organization suspended for non-payment
     * cannot enter the portal, yet its owner must still be able to pay.
     */
    public function renew(Request $request): View|RedirectResponse
    {
        $organizationId = (int) $request->session()->get('active_organization_id');

        $membership = $request->user()->memberships()->active()
            ->where('organization_id', $organizationId)
            ->whereHas('roles', fn ($query) => $query->where('key', 'owner'))
            ->with('organization')
            ->firstOrFail();

        return $this->startCheckout($request, $membership->organization);
    }

    private function startCheckout(Request $request, Organization $organization): View|RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);

        try {
            $subscription = $this->subscriptions->checkout($organization, $plan, $data['coupon'] ?? null);
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (RequestException) {
            return back()->with('error', 'Razorpay is not available right now. Please try again.');
        }

        return view('app.subscription.checkout', ['subscription' => $subscription, 'plan' => $plan]);
    }
}
