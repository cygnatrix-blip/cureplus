@foreach ($announcements as $announcement)
    <div class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 print:hidden" role="status">
        <div><p class="font-semibold">{{ $announcement->title }}</p><p class="whitespace-pre-line">{{ $announcement->body }}</p></div>
        <form method="POST" action="{{ route('app.announcements.dismiss', $announcement) }}">@csrf<button class="font-semibold" aria-label="Dismiss">&times;</button></form>
    </div>
@endforeach
