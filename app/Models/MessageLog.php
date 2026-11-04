<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $sent_at
 */
class MessageLog extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'channel', 'event', 'recipient_masked', 'status', 'error', 'provider_id', 'dedupe_key', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function allowsNullOrganization(): bool
    {
        return true;
    }
}
