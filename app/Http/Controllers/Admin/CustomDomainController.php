<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\AuditLogger;
use App\Services\Domains\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomDomainController extends Controller
{
    public function index(): View
    {
        return view('admin.domains.index', [
            'domains' => CustomDomain::withoutTenancy()->with('organization')->orderByRaw("status = 'awaiting_parking' desc")->latest('id')->paginate(50),
            'parkedCount' => CustomDomain::withoutTenancy()->where('parked_in_hosting', true)->count(),
            'parkedLimit' => DomainService::PARKED_LIMIT,
        ]);
    }

    public function check(int $domain, DomainService $domains): RedirectResponse
    {
        $result = $domains->checkDns(CustomDomain::withoutTenancy()->findOrFail($domain));

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'DNS is correct.' : (string) $result['problem']);
    }

    public function park(int $domain, DomainService $domains, AuditLogger $audit): RedirectResponse
    {
        $record = CustomDomain::withoutTenancy()->findOrFail($domain);

        $live = $domains->markParked($record);
        $audit->record('custom_domains.parked', $record, ['live' => $live]);

        return back()->with($live ? 'success' : 'status', $live ? 'Domain is live.' : 'Marked as parked. HTTPS is not answering yet, so wait for the certificate and press Retry.');
    }

    public function retry(int $domain, DomainService $domains): RedirectResponse
    {
        $live = $domains->activateIfHttpsWorks(CustomDomain::withoutTenancy()->findOrFail($domain));

        return back()->with($live ? 'success' : 'error', $live ? 'Domain is live.' : 'HTTPS is still not answering.');
    }
}
