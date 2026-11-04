<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.organizations.index', [
            'organizations' => $this->query($request)->withCount(['memberships', 'locations'])->paginate(25)->withQueryString(),
            'search' => $request->query('q'),
            'status' => $request->query('status'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->limit(5000)->get()->map(fn (Organization $organization) => [
            $organization->name, $organization->slug, $organization->type->label(), $organization->status->value, $organization->created_at?->toDateString(),
        ]);

        return Csv::download('organizations.csv', ['Name', 'Slug', 'Type', 'Status', 'Created'], $rows);
    }

    public function show(Organization $organization): View
    {
        return view('admin.organizations.show', [
            'organization' => $organization,
            'memberships' => $organization->memberships()->with(['user:id,name,email,mobile,two_factor_confirmed_at', 'roles:id,name'])->paginate(25),
            'locations' => $organization->locations()->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $organization->forceFill([
            'status' => OrganizationStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'],
        ])->save();

        return back()->with('success', 'Organization suspended.');
    }

    /**
     * @return Builder<Organization>
     */
    private function query(Request $request): Builder
    {
        $search = trim((string) $request->query('q'));
        $status = OrganizationStatus::tryFrom((string) $request->query('status'));

        return Organization::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id');
    }
}
