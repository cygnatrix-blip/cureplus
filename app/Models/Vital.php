<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Vital extends Model
{
    use Auditable, BelongsToOrganization;

    protected $fillable = ['organization_id', 'visit_id', 'bp_systolic', 'bp_diastolic', 'pulse', 'temperature_f', 'spo2', 'weight_kg', 'height_cm', 'recorded_by'];
}
