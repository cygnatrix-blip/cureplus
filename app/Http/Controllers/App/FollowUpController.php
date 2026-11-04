<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view') === 'missed' ? 'missed' : 'due';

        return view('app.follow-ups.index', [
            'view' => $view,
            'followUps' => FollowUp::query()
                ->when($view === 'missed',
                    fn ($query) => $query->where('status', 'missed'),
                    fn ($query) => $query->where('status', 'pending')->where('due_on', '<=', today()->addDays(7)->endOfDay()))
                ->with(['patient:id,name,mobile,patient_code', 'doctor:id,name'])
                ->orderBy('due_on')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['called', 'done', 'cancelled'])],
            'call_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $followUp->update($data);

        return back()->with('success', 'Follow-up updated.');
    }
}
