<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.medicines.index', [
            'medicines' => Medicine::query()
                ->when($search !== '', fn ($query) => $query->search($search))
                ->orderBy('generic_name')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Medicine::create($this->validated($request));

        return back()->with('success', 'Medicine added.');
    }

    public function update(Request $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($this->validated($request));

        return back()->with('success', 'Medicine updated.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:20480']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($column) => mb_strtolower(trim((string) $column)), (array) fgetcsv($handle, escape: ''));
        $added = 0;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

            if (empty($data['generic_name'])) {
                continue;
            }

            Medicine::query()->firstOrCreate(
                ['generic_name' => $data['generic_name'], 'brand_name' => $data['brand_name'] ?? null, 'strength' => $data['strength'] ?? null],
                ['form' => $data['form'] ?? null, 'manufacturer' => $data['manufacturer'] ?? null],
            );
            $added++;
        }

        fclose($handle);

        return back()->with('success', "Imported {$added} rows.");
    }

    public function requests(): View
    {
        return view('admin.medicines.requests', [
            'requests' => MedicineRequest::withoutTenancy()->where('status', 'pending')->oldest('id')->paginate(50),
        ]);
    }

    public function approve(int $request, Request $http, AuditLogger $audit): RedirectResponse
    {
        $item = MedicineRequest::withoutTenancy()->where('status', 'pending')->findOrFail($request);

        $medicine = Medicine::query()->firstOrCreate(
            ['generic_name' => $item->generic_name, 'brand_name' => $item->brand_name, 'strength' => $item->strength],
            ['form' => $item->form, 'manufacturer' => $item->manufacturer],
        );

        $item->forceFill(['status' => 'approved', 'reviewed_by' => $http->user()->id, 'reviewed_at' => now()])->save();
        $audit->record('medicine_requests.approved', $medicine, organizationId: (int) $item->organization_id);

        return back()->with('success', 'Added to the catalogue.');
    }

    public function reject(int $request, Request $http, AuditLogger $audit): RedirectResponse
    {
        $data = $http->validate(['reason' => ['required', 'string', 'max:255']]);
        $item = MedicineRequest::withoutTenancy()->where('status', 'pending')->findOrFail($request);

        $item->forceFill(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'reviewed_by' => $http->user()->id, 'reviewed_at' => now()])->save();
        $audit->record('medicine_requests.rejected', $item, organizationId: (int) $item->organization_id);

        return back()->with('success', 'Request rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:60'],
            'form' => ['nullable', 'string', 'max:40'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
