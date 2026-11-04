<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OrganizationCounter extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'counter_key', 'value'];
}
