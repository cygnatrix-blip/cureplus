@props(['status'])
@php($value = $status instanceof \BackedEnum ? $status->value : $status)
<span @class(['badge', 'badge-green' => $value === 'active', 'badge-amber' => $value === 'pending', 'badge-red' => in_array($value, ['suspended', 'inactive'], true)])>{{ ucfirst($value) }}</span>
