<form method="POST" action="{{ route('patient.language') }}">
    @csrf
    <select name="locale" class="input min-h-9 w-auto py-1 text-xs" data-auto-submit aria-label="{{ __('Language') }}">
        @foreach (config('cureplus.locales') as $code => $name)<option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>@endforeach
    </select>
</form>
