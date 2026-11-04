<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property list<string> $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
class ApiKey extends Model
{
    use BelongsToOrganization;

    public const SCOPES = [
        'catalogue.read' => 'Read the medicine catalogue',
        'prescriptions.read' => 'Read an organization\'s prescriptions',
    ];

    protected $fillable = ['organization_id', 'name', 'prefix', 'key_hash', 'scopes', 'expires_at', 'created_by'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function allowsNullOrganization(): bool
    {
        return true;
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
