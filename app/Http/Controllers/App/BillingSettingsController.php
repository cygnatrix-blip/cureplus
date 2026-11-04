<?php

namespace App\Http\Controllers\App;

use App\Enums\VisitType;
use App\Http\Controllers\Controller;
use App\Models\DoctorFee;
use App\Models\ServiceItem;
use App\Services\AuditLogger;
use App\Services\TenantDoctors;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(TenantDoctors $doctors): View
    {
        return view('app.billing-settings.index', [
            'fees' => DoctorFee::query()->get(),
            'doctors' => $doctors->all(),
            'visitTypes' => VisitType::cases(),
            'serviceItems' => ServiceItem::query()->orderBy('name')->paginate(50),
            'settings' => $this->tenant->organization()->settings ?? [],
        ]);
    }

    public function saveFees(Request $request, TenantDoctors $doctors): RedirectResponse
    {
        $data = $request->validate([
            'fees' => ['array'],
            'fees.*.*' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'follow_up_window_days' => ['required', 'integer', 'between:0,365'],
            'follow_up_discount_percent' => ['required', 'integer', 'between:0,100'],
        ]);

        $allowedDoctors = $doctors->all()->pluck('id')->map(fn ($id) => (string) $id)->push('default')->all();

        foreach ($data['fees'] ?? [] as $doctorKey => $types) {
            if (! in_array((string) $doctorKey, $allowedDoctors, true)) {
                throw ValidationException::withMessages(['fees' => 'Choose a doctor from your organization.']);
            }

            foreach ($types as $type => $amount) {
                $visitType = VisitType::tryFrom((string) $type);

                if ($visitType === null) {
                    continue;
                }

                $where = ['doctor_user_id' => $doctorKey === 'default' ? null : (int) $doctorKey, 'visit_type' => $visitType->value];

                if ($amount === null || $amount === '') {
                    DoctorFee::query()->where($where)->delete();
                } else {
                    DoctorFee::query()->updateOrCreate($where, ['amount_paise' => Money::toPaise($amount)]);
                }
            }
        }

        $organization = $this->tenant->organization();
        $organization->forceFill(['settings' => array_merge($organization->settings ?? [], [
            'follow_up_window_days' => (int) $data['follow_up_window_days'],
            'follow_up_discount_percent' => (int) $data['follow_up_discount_percent'],
        ])])->save();

        return back()->with('success', 'Fees saved.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        ServiceItem::create($this->validatedItem($request));

        return back()->with('success', 'Item added.');
    }

    public function destroyItem(ServiceItem $item, AuditLogger $audit): RedirectResponse
    {
        $item->delete();
        $audit->record('service_items.removed', $item);

        return back()->with('success', 'Item removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_taxable' => ['sometimes', 'boolean'],
            'gst_rate' => ['nullable', 'numeric', 'between:0,28'],
            'sac_hsn_code' => ['nullable', 'string', 'max:12'],
        ]);
        $taxable = (bool) ($data['is_taxable'] ?? false);

        return [
            'name' => $data['name'],
            'price_paise' => Money::toPaise($data['price']),
            'is_taxable' => $taxable,
            'gst_rate_bp' => $taxable ? (int) round(((float) ($data['gst_rate'] ?? 0)) * 100) : 0,
            'sac_hsn_code' => $data['sac_hsn_code'] ?? null,
        ];
    }
}
