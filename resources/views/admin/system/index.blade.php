<x-layouts.admin title="System configuration">
    <div class="mx-auto max-w-3xl space-y-4">
        <form method="POST" action="{{ route('admin.system.save') }}" class="card card-body space-y-3">
            @csrf
            <h2 class="font-semibold text-slate-900">Global settings</h2>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_approval" value="1" @checked(config('cureplus.organizations.require_approval'))> New organizations need approval</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="two_factor_all_staff" value="1" @checked($settings->get('security.two_factor_all_staff'))> Require 2FA for all staff (owners, doctors and admins always need it)</label>
            <div>
                <label class="label" for="legal_privacy">Privacy policy</label>
                <textarea class="input" id="legal_privacy" name="legal_privacy" rows="8">{{ $settings->get('legal.privacy') }}</textarea>
                <p class="hint">Plain text. Blank shows the built-in draft. Have counsel review this before launch.</p>
            </div>
            <div>
                <label class="label" for="legal_terms">Terms of service</label>
                <textarea class="input" id="legal_terms" name="legal_terms" rows="8">{{ $settings->get('legal.terms') }}</textarea>
            </div>
            <button class="btn btn-primary">Save</button>
        </form>
        <form method="POST" action="{{ route('admin.system.maintenance') }}" class="card card-body space-y-2" data-confirm="Change maintenance mode?">
            @csrf
            <h2 class="font-semibold text-slate-900">Maintenance mode</h2>
            <p class="text-sm text-slate-600">{{ $isDown ? 'The site is in maintenance mode.' : 'The site is up.' }}</p>
            <button class="btn btn-danger" name="action" value="{{ $isDown ? 'up' : 'down' }}">{{ $isDown ? 'Bring the site back up' : 'Turn on maintenance mode' }}</button>
        </form>
    </div>
</x-layouts.admin>
