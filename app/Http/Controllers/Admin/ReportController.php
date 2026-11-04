<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Services\AuditLogger;
use App\Services\Reports\PlatformReports;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(PlatformReports $reports): View
    {
        return view('admin.reports.index', [
            'mrr' => $reports->monthlyRecurringPaise(),
            'months' => $reports->lastTwelveMonths(),
            'churn' => $reports->churnLastThirtyDays(),
            'adoption' => $reports->featureAdoption(),
            'activeOrganizations' => $reports->activeOrganizations(),
            'money' => Money::class,
        ]);
    }

    public function changePlan(Request $request, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:plans,id']]);

        OrganizationSubscription::withoutTenancy()->updateOrCreate(
            ['organization_id' => $organization->id],
            ['plan_id' => $data['plan_id'], 'status' => 'active'],
        );

        $audit->record('subscriptions.plan_changed_by_admin', $organization, ['plan_id' => (int) $data['plan_id']]);

        return back()->with('success', 'Plan changed.');
    }
}
