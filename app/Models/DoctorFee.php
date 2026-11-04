<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DoctorFee extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'doctor_user_id', 'visit_type', 'amount_paise'];
}
