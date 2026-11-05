<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Specialties;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.templates.index', [
            'templates' => Template::withoutTenancy()->whereNull('organization_id')->orderBy('kind')->orderBy('name')->paginate(50),
            'events' => config('messages.events'),
        ]);
    }

    public function storeEmr(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'specialty' => ['required', Rule::in(Specialties::allowed())],
            'name' => ['required', 'string', 'max:120'],
            'complaints' => ['nullable', 'string', 'max:2000'],
            'history' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:500'],
        ]);

        Template::withoutTenancy()->create([
            'kind' => 'emr',
            'specialty' => $data['specialty'],
            'name' => $data['name'],
            'body' => ['complaints' => $data['complaints'] ?? '', 'history' => $data['history'] ?? '', 'diagnosis' => $data['diagnosis'] ?? ''],
        ]);

        return back()->with('success', 'Template added.');
    }

    public function saveMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(array_keys(config('messages.events')))],
            'text' => ['nullable', 'string', 'max:500'],
        ]);

        // Clearing the text returns the event to its built-in wording.
        if (blank($data['text'] ?? null)) {
            Template::withoutTenancy()->whereNull('organization_id')->where('kind', 'message')->where('template_key', $data['event'])->delete();
        } else {
            Template::withoutTenancy()->updateOrCreate(
                ['organization_id' => null, 'kind' => 'message', 'template_key' => $data['event']],
                ['name' => $data['event'], 'body' => ['text' => $data['text']]],
            );
        }

        Cache::forget('message-template.'.$data['event']);

        return back()->with('success', 'Message saved. Remember that SMS text must match your DLT-registered template.');
    }

    public function destroy(int $template): RedirectResponse
    {
        Template::withoutTenancy()->whereNull('organization_id')->findOrFail($template)->delete();

        return back()->with('success', 'Template removed.');
    }
}
