<?php

namespace App\Http\Controllers\App;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Billing\PlanLimits;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('app.locations.index', [
            'locations' => Location::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('app.locations.form', ['location' => new Location(['is_active' => true])]);
    }

    public function store(Request $request, TenantContext $tenant, PlanLimits $limits): RedirectResponse
    {
        try {
            $limits->assertRoomFor((int) $tenant->id(), 'locations', 'locations');
        } catch (PlanLimitException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Location::create($this->validated($request));

        return redirect()->route('app.locations.index')->with('success', 'Location added.');
    }

    public function edit(Location $location): View
    {
        return view('app.locations.form', ['location' => $location]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request));

        return redirect()->route('app.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location, TenantContext $tenant): RedirectResponse
    {
        if ($location->is_active && Location::query()->active()->count() === 1) {
            return back()->with('error', $tenant->organization()->type->locationNoun().' cannot be removed: it is your only active one.');
        }

        $location->delete();

        return redirect()->route('app.locations.index')->with('success', 'Location removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'digits:6'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'accepts_online_booking' => ['sometimes', 'boolean'],
            'timings.text' => ['nullable', 'string', 'max:255'],
            'letterhead.title' => ['nullable', 'string', 'max:150'],
            'letterhead.subtitle' => ['nullable', 'string', 'max:255'],
            'rx_pad.footer' => ['nullable', 'string', 'max:255'],
        ]) + ['is_active' => false, 'accepts_online_booking' => false];
    }
}
