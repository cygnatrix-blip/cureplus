<x-layouts.app title="Custom domain">
    <div class="mx-auto max-w-3xl space-y-4">
        <form method="POST" action="{{ route('app.domains.store') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">Use your own domain for booking</h2>
            <p class="text-sm text-slate-600">Patients book on your domain. Your staff keep signing in on {{ config('cureplus.domain') }}.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="hostname" label="Domain" placeholder="book.yourclinic.com" required />
                <div>
                    <label class="label" for="type">Type</label>
                    <select class="input" id="type" name="type">
                        <option value="subdomain">Subdomain (recommended), like book.yourclinic.com</option>
                        <option value="root">Root domain, like yourclinic.com</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary">Add domain</button>
        </form>
        <section class="card card-body text-sm">
            <h3 class="font-semibold text-slate-900">The DNS records to add</h3>
            <p class="mt-2 font-medium">For a subdomain:</p>
            <p class="font-mono text-xs">CNAME book → {{ $connectHost }}</p>
            <p class="mt-2 font-medium">For a root domain:</p>
            <p class="font-mono text-xs">A @ → {{ $ipv4 }}</p>
            <p class="font-mono text-xs">AAAA @ → {{ $ipv6 }} (optional)</p>
            <p class="mt-1 text-slate-600">Remove any other A or AAAA records on @.</p>
        </section>
        <section class="card">
            <div class="card-header"><h3 class="card-title">Your domains</h3></div>
            @forelse ($domains as $domain)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:px-5">
                    <div>
                        <p class="font-medium text-slate-900">{{ $domain->hostname }}</p>
                        <p class="text-slate-500">
                            @switch($domain->status)
                                @case('pending_dns') Waiting for your DNS record @break
                                @case('awaiting_parking') DNS is correct. Our team is connecting it. @break
                                @case('parked') Waiting for the security certificate @break
                                @case('active') Live @break
                                @case('dns_broken') DNS has changed, so this domain is offline @break
                            @endswitch
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST" action="{{ route('app.domains.check', $domain) }}">@csrf<button class="btn btn-sm btn-secondary">Check DNS</button></form>
                        <form method="POST" action="{{ route('app.domains.destroy', $domain) }}" data-confirm="Remove {{ $domain->hostname }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Remove</button></form>
                    </div>
                </div>
            @empty
                <x-empty title="No domains yet" />
            @endforelse
        </section>
        <a class="btn btn-ghost" href="{{ route('app.support.index', ['subject' => 'Help me connect my domain', 'category' => 'domain']) }}">Ask us to help</a>
    </div>
</x-layouts.app>
