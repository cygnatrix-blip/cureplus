<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Only an event's id and outcome are kept. Payloads hold customer details
 * that this table has no reason to retain.
 */
class WebhookEvent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'provider', 'event_id', 'event_type', 'status', 'detail'];

    public function allowsNullOrganization(): bool
    {
        return true;
    }
}
