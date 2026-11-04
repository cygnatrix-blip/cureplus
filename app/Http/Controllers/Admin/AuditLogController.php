<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.audit.index', [
            'logs' => $this->query($request)->paginate(50)->withQueryString(),
            'search' => $request->query('q'),
            'actorType' => $request->query('actor'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->limit(5000)->get()->map(fn (AuditLog $log) => [
            $log->created_at->toDateTimeString(), $log->organization_id, $log->actor_type, $log->actor_name,
            $log->impersonator_id, $log->action, $log->subject_type, $log->subject_id, $log->ip_address,
        ]);

        return Csv::download('platform-audit-log.csv', ['Time', 'Organization ID', 'Actor type', 'Actor', 'Impersonator ID', 'Action', 'Record', 'Record ID', 'IP'], $rows);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function query(Request $request): Builder
    {
        $search = trim((string) $request->query('q'));
        $actor = $request->query('actor');

        return AuditLog::withoutTenancy()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('action', 'like', "%{$search}%")
                ->orWhere('actor_name', 'like', "%{$search}%")))
            ->when(in_array($actor, ['user', 'platform_admin'], true), fn ($q) => $q->where('actor_type', $actor))
            ->latest('id');
    }
}
