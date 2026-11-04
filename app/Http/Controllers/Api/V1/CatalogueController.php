<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q'));

        return MedicineResource::collection(
            Medicine::query()->where('is_active', true)
                ->when($search !== '', fn ($query) => $query->search($search))
                ->orderBy('generic_name')
                ->paginate(100)
        );
    }
}
