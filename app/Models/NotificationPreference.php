<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property list<string> $muted_events
 */
class NotificationPreference extends Model
{
    protected $fillable = ['subscriber_type', 'subscriber_id', 'muted_events'];

    protected function casts(): array
    {
        return ['muted_events' => 'array'];
    }
}
