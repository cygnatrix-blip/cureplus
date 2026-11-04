<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->orderBy('price_paise')->get(),
            'coupons' => Coupon::query()->latest('id')->limit(100)->get(),
            'subscriptions' => OrganizationSubscription::withoutTenancy()->with(['plan', 'organization'])->latest('id')->paginate(25),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $plan = Plan::query()->create($this->validated($request) + ['code' => $request->validate(['code' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')]])['code']]);
        $audit->record('plans.created', $plan);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $plan->update($this->validated($request));
        $audit->record('plans.updated', $plan);

        return back()->with('success', 'Plan updated.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40'],
            'extra_trial_days' => ['nullable', 'integer', 'between:0,365'],
            'razorpay_offer_id' => ['nullable', 'string', 'max:60'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $data['code'] = Str::upper($data['code']);

        if (Coupon::query()->where('code', $data['code'])->exists()) {
            return back()->withErrors(['code' => 'This code already exists.']);
        }

        Coupon::query()->create($data + ['extra_trial_days' => 0]);

        return back()->with('success', 'Coupon created.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'billing_interval' => ['required', 'in:month,year'],
            'trial_days' => ['required', 'integer', 'between:0,365'],
            'razorpay_plan_id' => ['nullable', 'string', 'max:60'],
            'limit_doctors' => ['nullable', 'integer', 'min:0'],
            'limit_staff' => ['nullable', 'integer', 'min:0'],
            'limit_locations' => ['nullable', 'integer', 'min:0'],
            'limit_messages' => ['nullable', 'integer', 'min:0'],
            'features' => ['array'],
            'features.*' => ['string', Rule::in(array_keys(config('plans.features')))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'price_paise' => Money::toPaise($data['price']),
            'billing_interval' => $data['billing_interval'],
            'trial_days' => $data['trial_days'],
            'razorpay_plan_id' => $data['razorpay_plan_id'] ?? null,
            'limits' => [
                'doctors' => $data['limit_doctors'] ?? null,
                'staff' => $data['limit_staff'] ?? null,
                'locations' => $data['limit_locations'] ?? null,
                'messages_per_month' => $data['limit_messages'] ?? null,
            ],
            'features' => $data['features'] ?? [],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
