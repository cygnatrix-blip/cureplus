@props(['status'])
@php($value = $status instanceof \BackedEnum ? $status->value : $status)
<span @class(['badge', 'badge-green' => in_array($value, ['active', 'approved'], true), 'badge-amber' => in_array($value, ['pending', 'pending_verification'], true), 'badge-red' => in_array($value, ['suspended', 'inactive', 'rejected'], true)])>{{ ucfirst(str_replace('_', ' ', $value)) }}</span>
