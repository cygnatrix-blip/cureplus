<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon $created_at
 * @property array<string, mixed>|null $metadata
 *
 * Tenant scoped like everything else; platform pages use withoutTenancy().
 * organization_id is null for platform-level events.
 */
class AuditLog extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'actor_type', 'actor_id', 'actor_name', 'impersonator_id', 'action',
        'subject_type', 'subject_id', 'metadata', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Evidence must not be editable through the application.
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    public function allowsNullOrganization(): bool
    {
        return true;
    }

    /**
     * When a doctor reads a shared record, the organization that holds it must see that
     * in its own log, so the entry is written into that organization while acting for another.
     */
    public function allowsWriteAcrossOrganizations(): bool
    {
        return true;
    }
}
