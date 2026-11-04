<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'prescription_id', 'medicine_id', 'medicine_name', 'dosage', 'timing', 'duration_days', 'notes', 'position'];
}
