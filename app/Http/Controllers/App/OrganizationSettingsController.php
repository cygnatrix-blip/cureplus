<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function edit(): View
    {
        return view('app.settings.organization', ['organization' => $this->tenant->organization()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
        ], ['gstin.regex' => 'This does not look like a valid GSTIN.']);

        $organization = $this->tenant->organization();
        $organization->update(Arr::only($data, ['name', 'gstin']));

        return back()->with('success', 'Organization details saved.');
    }
}
