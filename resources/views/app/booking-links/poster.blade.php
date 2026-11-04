<x-layouts.app title="Poster">
    <div class="mb-4 text-right print:hidden"><button type="button" class="btn btn-primary" data-print>Print poster</button></div>
    <article class="card card-body mx-auto flex max-w-xl flex-col items-center gap-4 py-10 text-center print:border-0 print:shadow-none">
        <h2 class="text-3xl font-bold text-slate-900">{{ $organization->name }}</h2>
        <p class="text-xl text-slate-700">{{ $link['label'] === $organization->name ? 'Book your appointment online' : 'Book with '.$link['label'] }}</p>
        <div>{!! $qr !!}</div>
        <p class="text-lg">Scan with your phone camera</p>
        <p class="font-mono text-sm break-all">{{ $link['url'] }}</p>
    </article>
</x-layouts.app>
