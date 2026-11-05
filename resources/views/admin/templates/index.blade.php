<x-layouts.admin title="Templates">
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.templates.emr') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">New EMR template</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-specialty-select required />
                <x-field name="name" label="Name" required />
            </div>
            @foreach (['complaints' => 'Complaints', 'history' => 'History', 'diagnosis' => 'Diagnosis'] as $field => $label)
                <div><label class="label" for="{{ $field }}">{{ $label }}</label><textarea class="input" id="{{ $field }}" name="{{ $field }}" rows="2"></textarea></div>
            @endforeach
            <button class="btn btn-primary">Add template</button>
        </form>
        <section class="card">
            <div class="card-header"><h2 class="card-title">Platform templates</h2></div>
            @forelse ($templates as $template)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-2 text-sm last:border-b-0">
                    <span>{{ strtoupper($template->kind) }} · {{ $template->specialty }} {{ $template->name }}</span>
                    <form method="POST" action="{{ route('admin.templates.destroy', $template->id) }}" data-confirm="Remove this template?">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Remove</button></form>
                </div>
            @empty
                <x-empty title="No templates" />
            @endforelse
        </section>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
    <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Message wording</h2></div>
        <p class="px-4 pt-3 text-sm text-slate-600 sm:px-5">Applies to SMS, email and push. SMS must match your DLT-registered template, and WhatsApp uses templates approved in Meta Business Manager. Clear a box to go back to the default.</p>
        @foreach ($events as $key => $event)
            @php($saved = $templates->first(fn ($template) => $template->kind === 'message' && $template->template_key === $key))
            <form method="POST" action="{{ route('admin.templates.message') }}" class="space-y-2 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:px-5">
                @csrf
                <input type="hidden" name="event" value="{{ $key }}">
                <label class="label" for="text-{{ $key }}">{{ $event['label'] }}</label>
                <textarea class="input" id="text-{{ $key }}" name="text" rows="2" placeholder="{{ $event['text'] }}">{{ $saved?->body['text'] ?? '' }}</textarea>
                <button class="btn btn-sm btn-secondary">Save</button>
            </form>
        @endforeach
    </section>
</x-layouts.admin>
