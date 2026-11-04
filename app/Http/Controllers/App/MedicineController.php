<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\FavouriteMedicine;
use App\Models\Medicine;
use App\Models\MedicineRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('app.medicines.index', [
            'favourites' => FavouriteMedicine::query()->with('medicine')->latest('id')->limit(200)->get(),
            'results' => mb_strlen($search) >= 2 ? Medicine::query()->where('is_active', true)->search($search)->limit(25)->get() : collect(),
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
