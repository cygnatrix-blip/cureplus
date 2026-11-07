{{-- Day checkboxes with shortcuts. The shortcuts need JavaScript; the checkboxes work without it. --}}
@php($checkedDays = array_map('intval', (array) $checked))
<fieldset class="space-y-2">
    <legend class="label">{{ $legend }}</legend>
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Models\DoctorAvailability::DAYS as $index => $day)
            <label class="flex min-h-11 min-w-14 cursor-pointer items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-medium has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-brand-500">
                <input class="sr-only" type="checkbox" name="{{ $name }}[]" value="{{ $index }}" @checked(in_array($index, $checkedDays, true))>
                <span title="{{ $day }}">{{ substr($day, 0, 3) }}</span>
            </label>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-2 text-sm">
        <button type="button" class="btn btn-sm btn-ghost" data-pick-days="1,2,3,4,5">Mon–Fri</button>
        <button type="button" class="btn btn-sm btn-ghost" data-pick-days="1,2,3,4,5,6">Mon–Sat</button>
        <button type="button" class="btn btn-sm btn-ghost" data-pick-days="0,1,2,3,4,5,6">Every day</button>
        <button type="button" class="btn btn-sm btn-ghost" data-pick-days="">Clear</button>
    </div>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
