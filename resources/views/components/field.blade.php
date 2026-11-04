@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'fieldId' => null])
@php($key = str_replace(['[', ']'], ['.', ''], $name))
@php($id = $fieldId ?? str_replace('.', '_', $key))
<div>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($key, $value) }}" @endif
        @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge(['class' => 'input']) }}>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($key)<p id="{{ $id }}-error" class="field-error">{{ $message }}</p>@enderror
</div>
