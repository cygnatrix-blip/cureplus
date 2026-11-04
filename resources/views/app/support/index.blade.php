<x-layouts.app title="Support">
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.support.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">Raise a ticket</h2>
            <x-field name="subject" label="Subject" :value="$presetSubject" required />
            <div>
                <label class="label" for="category">Topic</label>
                <select class="input" id="category" name="category">
                    @foreach (['general' => 'General', 'razorpay' => 'Razorpay setup', 'domain' => 'Custom domain', 'billing' => 'Subscription and billing', 'bug' => 'Something is broken'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $presetCategory) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="body">How can we help?</label>
                <textarea class="input" id="body" name="body" rows="5" required>{{ old('body') }}</textarea>
                <p class="hint">Please do not include patient details.</p>
                @error('body')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Send</button>
        </form>
        <section class="card">
            <div class="card-header"><h2 class="card-title">Your tickets</h2></div>
            @forelse ($tickets as $ticket)
                <a class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50 last:border-b-0" href="{{ route('app.support.show', $ticket) }}">
                    <span class="min-w-0 truncate">{{ $ticket->subject }}</span>
                    <span class="badge badge-slate">{{ ucfirst($ticket->status) }}</span>
                </a>
            @empty
                <x-empty title="No tickets yet" />
            @endforelse
        </section>
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
