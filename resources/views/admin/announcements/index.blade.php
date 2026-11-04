<x-layouts.admin title="Announcements">
    <form method="POST" action="{{ route('admin.announcements.store') }}" class="card card-body max-w-2xl space-y-3">
        @csrf
        <h2 class="font-semibold text-slate-900">New announcement</h2>
        <x-field name="title" label="Title" required />
        <div>
            <label class="label" for="body">Message</label>
            <textarea class="input" id="body" name="body" rows="3" required>{{ old('body') }}</textarea>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-field name="organization_slugs" label="Only these organizations (link names, comma separated)" hint="Leave blank for everyone." />
        <x-field name="ends_at" label="Stop showing on" type="date" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_push" value="1"> Also send a push notification</label>
        <button class="btn btn-primary">Publish</button>
    </form>
    <section class="card mt-4">
        @forelse ($announcements as $announcement)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                <div><p class="font-medium">{{ $announcement->title }}</p><p class="text-slate-600">{{ $announcement->body }}</p><p class="text-xs text-slate-500">{{ $announcement->organization_ids === null ? 'Everyone' : count($announcement->organization_ids).' organizations' }} · {{ $announcement->starts_at->format('d M Y') }}</p></div>
                <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" data-confirm="Remove this announcement?">@csrf @method('DELETE')<button class="btn btn-sm btn-secondary">Remove</button></form>
            </div>
        @empty
            <x-empty title="No announcements" />
        @endforelse
    </section>
    <div class="mt-4">{{ $announcements->links() }}</div>
</x-layouts.admin>
