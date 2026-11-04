<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        return view('app.support.index', [
            'tickets' => SupportTicket::query()->latest('updated_at')->paginate(25),
            'presetSubject' => $request->query('subject'),
            'presetCategory' => $request->query('category', 'general'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:general,billing,razorpay,domain,bug'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            'opened_by' => $request->user()->id,
            'subject' => $data['subject'],
            'category' => $data['category'],
        ]);
        $ticket->messages()->create(['organization_id' => $ticket->organization_id, 'author_type' => 'user', 'author_name' => $request->user()->name, 'body' => $data['body']]);

        return redirect()->route('app.support.show', $ticket)->with('success', 'Your ticket has been raised.');
    }

    public function show(SupportTicket $ticket): View
    {
        return view('app.support.show', ['ticket' => $ticket->load('messages')]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->messages()->create(['organization_id' => $ticket->organization_id, 'author_type' => 'user', 'author_name' => $request->user()->name, 'body' => $data['body']]);
        $ticket->update(['status' => 'open', 'closed_at' => null]);

        return back()->with('success', 'Reply sent.');
    }
}
