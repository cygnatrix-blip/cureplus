<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed> $config
 * @property Carbon|null $last_tested_at
 */
class OrganizationIntegration extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'kind', 'config', 'is_enabled'];

    // Hidden everywhere: toArray and JSON can never carry secrets to a page or a log.
    protected $hidden = ['config'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_enabled' => 'boolean', 'last_tested_at' => 'datetime'];
    }

    /**
     * Shows that a secret is set without showing the secret.
     */
    public function masked(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && $value !== '' ? '••••'.substr($value, -4) : null;
    }
}
