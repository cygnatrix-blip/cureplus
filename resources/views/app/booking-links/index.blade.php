<x-layouts.app title="Booking links">
    <x-page-header title="Booking links and QR codes" subtitle="Share these with patients, or print the poster for your reception." />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($links as $link)
            <section class="card card-body flex flex-col items-center gap-3 text-center">
                <div class="rounded-lg border border-slate-200 bg-white p-2">{!! $link['qr'] !!}</div>
                <p class="font-semibold text-slate-900">{{ $link['label'] }}</p>
                <a class="text-xs break-all text-brand-700 underline" href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['url'] }}</a>
                <div class="flex flex-wrap justify-center gap-2">
                    <a class="btn btn-sm btn-secondary" href="{{ route('app.booking-links.download', ['link' => $link['key']]) }}">Download QR</a>
                    <a class="btn btn-sm btn-secondary" href="{{ route('app.booking-links.poster', ['link' => $link['key']]) }}">Poster</a>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
