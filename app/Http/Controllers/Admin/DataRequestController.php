<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\PatientAccount;
use App\Services\AuditLogger;
use App\Services\Portal\AccountEraser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.data-requests.index', [
            'requests' => DataRequest::query()->with('account:id,email')->orderByRaw("status = 'open' desc")->latest('id')->paginate(25),
        ]);
    }

    public function resolve(Request $request, DataRequest $dataRequest, AccountEraser $eraser, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:completed,rejected'],
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        abort_unless($dataRequest->status === 'open', 422);

        $account = PatientAccount::query()->find($dataRequest->patient_account_id);

        if ($dataRequest->type === 'deletion' && $data['status'] === 'completed' && $account) {
            $eraser->erase($account);
            $audit->record('patient_accounts.erased', $dataRequest);
        }

        $dataRequest->forceFill($data + ['resolved_by' => $request->user('platform')->id, 'resolved_at' => now()])->save();

        return back()->with('success', 'Request resolved.');
    }
}
