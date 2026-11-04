<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BreachIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BreachController extends Controller
{
    public function index(): View
    {
        return view('admin.breaches.index', ['incidents' => BreachIncident::query()->latest('detected_at')->paginate(25)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'detected_at' => ['required', 'date', 'before_or_equal:now'],
            'affected_count' => ['nullable', 'integer', 'min:0'],
        ]);

        BreachIncident::query()->create($data + ['created_by' => $request->user('platform')->id]);

        return back()->with('success', 'Incident recorded. The clock on your legal duties to notify has started.');
    }

    public function update(Request $request, BreachIncident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,contained,resolved'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'affected_count' => ['nullable', 'integer', 'min:0'],
            'board_notified' => ['sometimes', 'boolean'],
            'patients_notified' => ['sometimes', 'boolean'],
        ]);

        $incident->fill([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'affected_count' => $data['affected_count'] ?? $incident->affected_count,
            'board_notified_at' => ($data['board_notified'] ?? false) ? ($incident->board_notified_at ?? now()) : $incident->board_notified_at,
            'patients_notified_at' => ($data['patients_notified'] ?? false) ? ($incident->patients_notified_at ?? now()) : $incident->patients_notified_at,
            'resolved_at' => $data['status'] === 'resolved' ? ($incident->resolved_at ?? now()) : null,
        ])->save();

        return back()->with('success', 'Incident updated.');
    }
}
