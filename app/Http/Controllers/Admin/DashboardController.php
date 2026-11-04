<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\SecurityEvent;
use App\Models\SupportTicket;
use App\Models\SystemHeartbeat;
use App\Services\Reports\PlatformReports;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(PlatformReports $reports): View
    {
        return view('admin.dashboard', [
            'activeOrganizations' => Organization::query()->where('status', OrganizationStatus::Approved)->count(),
            'pendingOrganizations' => Organization::query()->where('status', OrganizationStatus::PendingVerification)->count(),
            'doctors' => Membership::withoutTenancy()->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->where('key', 'doctor'))->distinct()->count('user_id'),
            'signupsThisMonth' => Organization::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'failedLogins24h' => SecurityEvent::query()->where('type', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
            'mrr' => $reports->monthlyRecurringPaise(),
            'failedPayments' => $reports->failedPayments(),
            'patients' => Patient::withoutTenancy()->count(),
            'openTickets' => SupportTicket::withoutTenancy()->where('status', 'open')->count(),
            'queueBacklog' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'heartbeat' => SystemHeartbeat::query()->find('scheduler')?->beat_at,
        ]);
    }
}
