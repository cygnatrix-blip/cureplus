<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Billing\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    public function index(): View
    {
        return view('admin.feature-flags.index', [
            'flags' => FeatureFlag::withoutTenancy()->with('organization')->latest('id')->paginate(50),
            'features' => config('plans.features'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit, FeatureGate $gate): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::in(array_keys(config('plans.features')))],
            'organization_slug' => ['nullable', 'string', 'max:80'],
            'plan_code' => ['nullable', Rule::exists('plans', 'code')],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $organization = null;

        if (! empty($data['organization_slug'])) {
            $organization = Organization::query()->where('slug', $data['organization_slug'])->first();

            if ($organization === null) {
                return back()->withErrors(['organization_slug' => 'No organization has this link name.'])->withInput();
            }
        } elseif (empty($data['plan_code'])) {
            return back()->withErrors(['plan_code' => 'Choose a plan or an organization.'])->withInput();
        }

        FeatureFlag::withoutTenancy()->updateOrCreate(
            ['feature' => $data['feature'], 'organization_id' => $organization?->id, 'plan_code' => $organization ? null : $data['plan_code']],
            ['is_enabled' => $data['is_enabled']],
        );

        $gate->forget();
        $audit->record('feature_flags.saved', $organization, ['feature' => $data['feature'], 'plan' => $data['plan_code'] ?? null, 'enabled' => (bool) $data['is_enabled']]);

        return back()->with('success', 'Flag saved.');
    }

    public function destroy(int $flag): RedirectResponse
    {
        FeatureFlag::withoutTenancy()->findOrFail($flag)->delete();

        return back()->with('success', 'Flag removed.');
    }
}
