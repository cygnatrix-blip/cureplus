<x-layouts.admin :title="$ticket->subject">
    <div class="mx-auto max-w-2xl space-y-3">
        <x-page-header :title="$ticket->subject" :subtitle="$ticket->organization?->name.' · '.ucfirst($ticket->status)"><a class="btn btn-ghost" href="{{ route('admin.support.index') }}">Back</a></x-page-header>
        @foreach ($messages as $message)
            <article @class(['card card-body', 'border-brand-200 bg-brand-50' => $message->author_type === 'admin'])>
                <p class="text-xs text-slate-500">{{ $message->author_name }} · {{ $message->created_at?->format('d M Y H:i') }}</p>
                <p class="mt-1 text-sm whitespace-pre-line">{{ $message->body }}</p>
            </article>
        @endforeach
        <form method="POST" action="{{ route('admin.support.reply', $ticket->id) }}" class="card card-body space-y-2">
            @csrf
            <textarea class="input" name="body" rows="3" required aria-label="Your reply"></textarea>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="close" value="1"> Close the ticket</label>
            <button class="btn btn-primary">Reply</button>
        </form>
    </div>
</x-layouts.admin>
