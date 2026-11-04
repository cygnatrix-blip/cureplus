<?php

namespace App\Http\Resources;

use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Medicine
 */
class MedicineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generic_name' => $this->generic_name,
            'brand_name' => $this->brand_name,
            'strength' => $this->strength,
            'form' => $this->form,
            'manufacturer' => $this->manufacturer,
        ];
    }
}
