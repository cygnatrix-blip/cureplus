@php
    $iconPath = \App\Models\Organization::query()->whereKey($organizationId)->value('icon_192_path');
    $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'));
    $iconData = $iconPath && $disk->exists($iconPath) ? base64_encode((string) $disk->get($iconPath)) : null;
@endphp
@if ($iconData)<img src="data:image/png;base64,{{ $iconData }}" alt="" style="height: 48px; width: 48px; float: right;">@endif
