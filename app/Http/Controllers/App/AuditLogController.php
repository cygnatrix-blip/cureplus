<?php

namespace App\Http\Controllers\App;

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
        return view('app.audit.index', [
            'logs' => $this->query($request)->paginate(50)->withQueryString(),
            'search' => $request->query('q'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->limit(5000)->get()->map(fn (AuditLog $log) => [
            $log->created_at->toDateTimeString(), $log->actor_name, $log->action, $log->subject_type, $log->subject_id, $log->ip_address,
        ]);

        return Csv::download('audit-log.csv', ['Time', 'Who', 'Action', 'Record', 'Record ID', 'IP'], $rows);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function query(Request $request): Builder
    {
        $search = trim((string) $request->query('q'));

        return AuditLog::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('action', 'like', "%{$search}%")
                ->orWhere('actor_name', 'like', "%{$search}%")))
            ->latest('id');
    }
}
