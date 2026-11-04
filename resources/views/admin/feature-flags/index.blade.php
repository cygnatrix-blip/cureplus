<x-layouts.admin title="Feature flags">
    <form method="POST" action="{{ route('admin.feature-flags.store') }}" class="card card-body max-w-2xl space-y-3">
        @csrf
        <h2 class="font-semibold text-slate-900">Set a flag</h2>
        <p class="text-sm text-slate-600">An organization's flag beats its plan's. Without a flag, the plan's feature list decides.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label" for="feature">Feature</label>
                <select class="input" id="feature" name="feature">@foreach ($features as $key => $feature)<option value="{{ $key }}">{{ $feature['label'] }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label" for="is_enabled">Setting</label>
                <select class="input" id="is_enabled" name="is_enabled"><option value="1">On</option><option value="0">Off</option></select>
            </div>
            <x-field name="organization_slug" label="Organization link name" />
            @if (config('plans.enabled'))<x-field name="plan_code" label="or plan code" />@endif
        </div>
        <button class="btn btn-primary">Save flag</button>
    </form>
    <section class="card mt-4">
        @if ($flags->isEmpty())
            <x-empty title="No flags set" text="Every feature is on for every practice." />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Feature</th><th>Applies to</th><th>Setting</th><th></th></tr></thead>
                <tbody>
                    @foreach ($flags as $flag)
                        <tr>
                            <td>{{ $features[$flag->feature]['label'] ?? $flag->feature }}</td>
                            <td>{{ $flag->organization?->name ?? 'Plan: '.$flag->plan_code }}</td>
                            <td>{{ $flag->is_enabled ? 'On' : 'Off' }}</td>
                            <td class="text-right"><form method="POST" action="{{ route('admin.feature-flags.destroy', $flag->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Remove</button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
    <div class="mt-4">{{ $flags->links() }}</div>
</x-layouts.admin>
