@props(['name' => 'specialty', 'label' => 'Specialty', 'value' => null, 'placeholder' => 'Choose a specialty'])
@php
    $selected = old($name, $value);
    $isListed = in_array($selected, [...\App\Support\Specialties::all(), config('specialties.other')], true);
@endphp
<div>
    <label for="{{ $name }}" class="label">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $attributes->merge(['class' => 'input']) }}>
        <option value="">{{ $placeholder }}</option>
        @if (filled($selected) && ! $isListed)<option value="{{ $selected }}" selected>{{ $selected }}</option>@endif
        @foreach (\App\Support\Specialties::bySystem() as $system => $specialties)
            <optgroup label="{{ $system }}">
                @foreach ($specialties as $specialty)<option value="{{ $specialty }}" @selected($selected === $specialty)>{{ $specialty }}</option>@endforeach
            </optgroup>
        @endforeach
        <option value="{{ config('specialties.other') }}" @selected($selected === config('specialties.other'))>{{ config('specialties.other') }}</option>
    </select>
    @error($name)<p id="{{ $name }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
