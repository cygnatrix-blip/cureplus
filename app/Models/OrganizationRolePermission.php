<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class OrganizationRolePermission extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'role_id', 'permission_id', 'granted'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
