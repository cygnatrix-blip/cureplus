<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('admin.support.index', [
            'tickets' => SupportTicket::withoutTenancy()
                ->when(in_array($status, ['open', 'answered', 'closed'], true), fn ($query) => $query->where('status', $status))
                ->with('organization')
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(int $ticket): View
    {
        $record = SupportTicket::withoutTenancy()->with('organization')->findOrFail($ticket);

        return view('admin.support.show', ['ticket' => $record, 'messages' => $record->messages()->withoutTenancy()->get()]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'close' => ['sometimes', 'boolean']]);
        $record = SupportTicket::withoutTenancy()->findOrFail($ticket);
        $admin = $request->user('platform');

        $record->messages()->create(['organization_id' => $record->organization_id, 'author_type' => 'admin', 'author_name' => $admin->name, 'body' => $data['body']]);

        $close = (bool) ($data['close'] ?? false);
        $record->forceFill([
            'status' => $close ? 'closed' : 'answered',
            'closed_at' => $close ? now() : null,
            'assigned_admin_id' => $record->assigned_admin_id ?? $admin->id,
        ])->save();

        return back()->with('success', 'Reply sent.');
    }
}
