<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Vital extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = ['organization_id', 'visit_id', 'bp_systolic', 'bp_diastolic', 'pulse', 'temperature_f', 'spo2', 'weight_kg', 'height_cm', 'recorded_by'];

    /**
     * The readings that were taken, labelled for a prescription, with BMI when weight and height are both known.
     *
     * @return array<string, string>
     */
    public function readings(): array
    {
        $number = fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');

        $readings = array_filter([
            'BP' => $this->bp_systolic && $this->bp_diastolic ? "{$this->bp_systolic}/{$this->bp_diastolic} mmHg" : null,
            'Pulse' => $this->pulse ? "{$this->pulse}/min" : null,
            'Temp' => $this->temperature_f ? $number($this->temperature_f).' °F' : null,
            'SpO2' => $this->spo2 ? "{$this->spo2}%" : null,
            'Weight' => $this->weight_kg ? $number($this->weight_kg).' kg' : null,
            'Height' => $this->height_cm ? $number($this->height_cm).' cm' : null,
        ]);

        if ($this->weight_kg && $this->height_cm) {
            $readings['BMI'] = $number((float) $this->weight_kg / (((float) $this->height_cm / 100) ** 2));
        }

        return $readings;
    }
}
