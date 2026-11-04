{{-- Every value is checked by BrandTheme, so this block cannot carry anything but colours and a radius. --}}
@php($css = $organization ? \App\Support\BrandTheme::css($organization->brand_color, $organization->branding['accent_color'] ?? null, $organization->branding['corners'] ?? null) : '')
@if ($css !== '')<style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! $css !!}</style>@endif
