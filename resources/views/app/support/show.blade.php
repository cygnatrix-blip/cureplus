<x-layouts.app :title="$ticket->subject">
    <div class="mx-auto max-w-2xl space-y-3">
        <x-page-header :title="$ticket->subject" :subtitle="ucfirst($ticket->status)"><a class="btn btn-ghost" href="{{ route('app.support.index') }}">Back</a></x-page-header>
        @foreach ($ticket->messages as $message)
            <article @class(['card card-body', 'border-brand-200 bg-brand-50' => $message->author_type === 'admin'])>
                <p class="text-xs text-slate-500">{{ $message->author_type === 'admin' ? 'CurePlus support' : $message->author_name }} · {{ $message->created_at?->format('d M Y H:i') }}</p>
                <p class="mt-1 text-sm whitespace-pre-line">{{ $message->body }}</p>
            </article>
        @endforeach
        <form method="POST" action="{{ route('app.support.reply', $ticket) }}" class="card card-body space-y-2">
            @csrf
            <textarea class="input" name="body" rows="3" required aria-label="Your reply"></textarea>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
            <button class="btn btn-primary">Reply</button>
        </form>
    </div>
</x-layouts.app>
