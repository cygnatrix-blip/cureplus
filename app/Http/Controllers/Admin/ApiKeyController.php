<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys.index', [
            'keys' => ApiKey::withoutTenancy()->with('organization')->latest('id')->paginate(30),
            'scopes' => ApiKey::SCOPES,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization_slug' => ['nullable', 'string', 'max:80'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(array_keys(ApiKey::SCOPES))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $organization = null;

        if (! blank($data['organization_slug'] ?? null)) {
            $organization = Organization::query()->where('slug', $data['organization_slug'])->first();

            if ($organization === null) {
                return back()->withErrors(['organization_slug' => 'No organization has this link name.'])->withInput();
            }
        }

        $token = 'cpk_'.Str::random(48);

        $key = ApiKey::withoutTenancy()->create([
            'organization_id' => $organization?->id,
            'name' => $data['name'],
            'prefix' => substr($token, 0, 10),
            'key_hash' => hash('sha256', $token),
            'scopes' => $data['scopes'],
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $request->user('platform')->id,
        ]);

        $audit->record('api_keys.created', $key, ['scopes' => $data['scopes']]);

        // Flashed once. Only the hash is stored, so it can never be shown again.
        return back()->with('new_api_key', $token);
    }

    public function revoke(int $key, AuditLogger $audit): RedirectResponse
    {
        $record = ApiKey::withoutTenancy()->findOrFail($key);
        $record->forceFill(['revoked_at' => now()])->save();
        $audit->record('api_keys.revoked', $record);

        return back()->with('success', 'Key revoked.');
    }
}
