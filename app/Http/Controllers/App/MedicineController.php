<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\FavouriteMedicine;
use App\Models\Medicine;
use App\Models\MedicineRequest;
use App\Support\Specialties;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $specialty = in_array($request->query('specialty'), Specialties::all(), true) ? (string) $request->query('specialty') : '';
        $system = in_array($request->query('system'), Specialties::systems(), true) ? (string) $request->query('system') : '';
        $hasFilter = mb_strlen($search) >= 2 || $specialty !== '' || $system !== '';

        return view('app.medicines.index', [
            'favourites' => FavouriteMedicine::query()->with('medicine')->latest('id')->limit(200)->get(),
            'results' => $hasFilter
                ? Medicine::query()->where('is_active', true)
                    ->when(mb_strlen($search) >= 2, fn ($query) => $query->search($search))
                    ->when($specialty !== '', fn ($query) => $query->where('specialty', $specialty))
                    ->when($system !== '', fn ($query) => $query->where('system', $system))
                    ->rankedFor($request->user()->doctorProfile?->specialty, $search)
                    ->paginate(50)
                    ->withQueryString()
                : null,
            'specialty' => $specialty,
            'system' => $system,
            'requests' => MedicineRequest::query()->latest('id')->limit(20)->get(),
            'search' => $search,
        ]);
    }

    public function favourite(Request $request): RedirectResponse
    {
        $data = $request->validate(['medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')]]);

        FavouriteMedicine::query()->firstOrCreate($data);

        return back()->with('success', 'Added to favourites.');
    }

    public function unfavourite(FavouriteMedicine $favourite): RedirectResponse
    {
        $favourite->delete();

        return back()->with('success', 'Removed from favourites.');
    }

    public function request(Request $request): RedirectResponse
    {
        MedicineRequest::create($request->validate([
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:60'],
            'form' => ['nullable', 'string', 'max:40'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ]) + ['requested_by' => $request->user()->id]);

        return back()->with('success', 'Request sent to the catalogue team.');
    }
}
