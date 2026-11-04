<label class="flex items-start gap-2 text-sm text-slate-700">
    <input type="checkbox" name="consent" value="1" class="mt-1" @checked(old('consent') || ($checked ?? false))>
    <span>{{ __('I agree that :clinic may use my details to book and provide my care, as described in the privacy policy.', ['clinic' => $organization->name]) }}</span>
</label>
@error('consent')<p class="field-error">{{ $message }}</p>@enderror
