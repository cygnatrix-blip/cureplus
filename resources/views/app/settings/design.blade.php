@php($branding = $organization->branding ?? [])
<x-layouts.app title="Website and design">
    <x-page-header title="Website and design" subtitle="Your own website, booking pages and patient dashboard. Sections you leave empty are hidden.">
        @if ($organization->isActive())<a class="btn btn-secondary" href="{{ route('clinic.home', $organization->slug) }}" target="_blank" rel="noopener">Open my website</a>@else<span class="text-sm text-slate-500">Your website goes live once you are verified.</span>@endif
    </x-page-header>
    <form method="POST" action="{{ route('app.settings.design.update') }}" enctype="multipart/form-data" class="grid items-start gap-5 lg:grid-cols-2"
        x-data="brandDesigner" data-primary="{{ old('brand_color', $organization->brand_color ?: '#088575') }}" data-accent="{{ old('accent_color', $branding['accent_color'] ?? '#f59e0b') }}" data-name="{{ $organization->name }}" data-tagline="{{ old('tagline', $branding['tagline'] ?? '') }}" data-can-read-jpeg="{{ $canReadJpeg ? '1' : '0' }}" data-logo="{{ $organization->icon_192_path ? route('app.settings.design.image', 'logo') : '' }}" data-cover="{{ $organization->cover_image_path ? route('app.settings.design.image', 'cover') : '' }}">
        @csrf @method('PUT')
        <div class="space-y-5">
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">Colours</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($presets as $name => [$primary, $accent])
                        <button type="button" class="btn btn-sm btn-secondary" data-primary="{{ $primary }}" data-accent="{{ $accent }}" @click="applyPreset">{{ $name }}</button>
                    @endforeach
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="brand_color">Main colour</label>
                        <div class="flex gap-2"><input type="color" class="h-11 w-14 flex-none cursor-pointer rounded-lg border border-slate-300" aria-label="Pick main colour" :value="primary" @input="setPrimary"><input class="input" id="brand_color" name="brand_color" value="{{ old('brand_color', $organization->brand_color ?: '#088575') }}" :value="primary" @input="setPrimary"></div>
                        @error('brand_color')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="accent_color">Accent colour</label>
                        <div class="flex gap-2"><input type="color" class="h-11 w-14 flex-none cursor-pointer rounded-lg border border-slate-300" aria-label="Pick accent colour" :value="accent" @input="setAccent"><input class="input" id="accent_color" name="accent_color" value="{{ old('accent_color', $branding['accent_color'] ?? '#f59e0b') }}" :value="accent" @input="setAccent"></div>
                        @error('accent_color')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="hint">If a colour is too pale for white button text, buttons use a darker shade of it so patients can still read them.</p>
                <fieldset>
                    <legend class="label">Corners</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($corners as $corner)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="corners" value="{{ $corner }}" @checked(old('corners', $branding['corners'] ?? 'soft') === $corner)> {{ ucfirst($corner) }}</label>
                        @endforeach
                    </div>
                </fieldset>
            </section>
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">Logo and photo</h3>
                <div>
                    <label class="label" for="logo">Logo</label>
                    <input class="input" id="logo" type="file" name="logo" accept="image/*" @change="prepareLogo">
                    <p class="hint">Any photo or image. Square works best. Also used on prescriptions, bills and as your app icon. It appears in the preview straight away; press Save design to keep it.</p>
                    @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                    @if ($organization->icon_192_path)<label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1"> Remove my logo</label>@endif
                </div>
                <div>
                    <label class="label" for="cover">Cover photo</label>
                    <input class="input" id="cover" type="file" name="cover" accept="image/*" @change="prepareCover">
                    <p class="hint">A wide photo of your clinic. It fills the top of your website, behind your tagline.</p>
                    @error('cover')<p class="field-error">{{ $message }}</p>@enderror
                    @if ($organization->cover_image_path)<label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> Remove my cover photo</label>@endif
                </div>
            </section>
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">Your words</h3>
                <div>
                    <label class="label" for="tagline">Tagline</label>
                    <input class="input" id="tagline" name="tagline" maxlength="120" value="{{ old('tagline', $branding['tagline'] ?? '') }}" :value="tagline" @input="setTagline" placeholder="Care close to home">
                    @error('tagline')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="about">About your practice</label>
                    <textarea class="input" id="about" name="about" rows="3" maxlength="800">{{ old('about', $branding['about'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="label" for="highlights">Highlights (up to 4, one per line)</label>
                    <textarea class="input" id="highlights" name="highlights" rows="4" placeholder="NABH accredited&#10;Open on Sundays">{{ old('highlights', implode("\n", $branding['highlights'] ?? [])) }}</textarea>
                    <p class="hint">Only claim what is true. Patients rely on these.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="contact_phone" label="Phone for patients" type="tel" :value="$branding['contact_phone'] ?? null" />
                    <x-field name="contact_email" label="Email for patients" type="email" :value="$branding['contact_email'] ?? null" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_fees" value="1" @checked(old('show_fees', $branding['show_fees'] ?? true))> Show consultation fees to patients</label>
            </section>
            <section class="card card-body space-y-3">
                <h3 class="font-semibold text-slate-900">Your website</h3>
                <x-field name="established_year" label="Year your practice began (optional)" type="number" min="1900" max="{{ now()->year }}" :value="$branding['established_year'] ?? null" hint="Shows as years of care. Only enter what is true." />
                <div>
                    <p class="label">Clinic photos (up to 6)</p>
                    @if ($branding['gallery'] ?? [])
                        <div class="mb-2 grid grid-cols-3 gap-2">
                            @foreach ($branding['gallery'] as $index => $path)
                                <label class="text-xs">
                                    <img src="{{ route('app.settings.design.image', 'gallery-'.$index) }}" alt="" class="h-20 w-full rounded-lg object-cover">
                                    <span class="mt-1 flex items-center gap-1"><input type="checkbox" name="remove_gallery[]" value="{{ $index }}"> Remove</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input class="input" id="gallery" type="file" name="gallery[]" accept="image/*" multiple @change="prepareGallery" aria-label="Add clinic photos">
                    @error('gallery')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <fieldset class="space-y-2">
                    <legend class="label">Services (up to 6)</legend>
                    @for ($row = 0; $row < 6; $row++)
                        <div class="grid gap-2 sm:grid-cols-3">
                            <input class="input" name="services[{{ $row }}][title]" maxlength="60" placeholder="Service" aria-label="Service {{ $row + 1 }}" value="{{ old("services.{$row}.title", $branding['services'][$row]['title'] ?? '') }}">
                            <input class="input sm:col-span-2" name="services[{{ $row }}][text]" maxlength="160" placeholder="One line about it (optional)" aria-label="Description of service {{ $row + 1 }}" value="{{ old("services.{$row}.text", $branding['services'][$row]['text'] ?? '') }}">
                        </div>
                    @endfor
                </fieldset>
                <fieldset class="space-y-2">
                    <legend class="label">Common questions (up to 5)</legend>
                    @for ($row = 0; $row < 5; $row++)
                        <div class="space-y-1">
                            <input class="input" name="faqs[{{ $row }}][question]" maxlength="150" placeholder="Question" aria-label="Question {{ $row + 1 }}" value="{{ old("faqs.{$row}.question", $branding['faqs'][$row]['question'] ?? '') }}">
                            <input class="input" name="faqs[{{ $row }}][answer]" maxlength="400" placeholder="Answer" aria-label="Answer {{ $row + 1 }}" value="{{ old("faqs.{$row}.answer", $branding['faqs'][$row]['answer'] ?? '') }}">
                        </div>
                    @endfor
                </fieldset>
                <p class="hint">Doctor photos are added under Staff and doctors, then Manage.</p>
            </section>
            <p class="field-error" x-show="imageError" x-text="imageError" role="alert"></p><button class="btn btn-primary" :disabled="preparing">Save design</button>
        </div>
        <aside class="lg:sticky lg:top-20">
            <p class="label">Live preview</p>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-sm" x-ref="preview">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-3">
                    <img x-show="hasLogo" :src="logo" alt="" class="h-8 w-8 rounded-lg object-contain"><span x-show="noLogo" class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white" x-text="initial"></span>
                    <span class="font-semibold text-slate-900" x-text="name"></span>
                </div>
                <div class="relative overflow-hidden bg-linear-to-br from-brand-800 to-brand-600 p-5 text-white">
                    <img x-show="hasCover" :src="cover" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25">
                    <p class="relative text-xl font-bold" x-text="previewTagline"></p>
                    <p class="relative mt-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium"><span class="h-2 w-2 rounded-full bg-accent-500"></span>Confirmation by email</p>
                </div>
                <div class="space-y-3 p-4">
                    <div class="card flex items-center gap-3 p-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800">RR</span>
                        <div><p class="font-semibold text-slate-900">Dr. Your Doctor</p><p class="text-xs text-slate-500">General Medicine</p></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs font-medium">
                        <span class="rounded-lg border border-slate-300 bg-white py-2">10:00 AM</span>
                        <span class="rounded-lg border border-brand-600 bg-brand-600 py-2 text-white">10:15 AM</span>
                        <span class="rounded-lg border border-slate-300 bg-white py-2">10:30 AM</span>
                    </div>
                    <span class="btn btn-primary w-full">Continue</span>
                </div>
            </div>
        </aside>
    </form>
</x-layouts.app>
