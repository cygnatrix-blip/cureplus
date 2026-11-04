<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = ['subscriber_type', 'subscriber_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'user_agent'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    protected function casts(): array
    {
        // Whoever holds these can send this device notifications, so they are encrypted.
        return ['endpoint' => 'encrypted', 'public_key' => 'encrypted', 'auth_token' => 'encrypted'];
    }

    public static function subscriberType(Model $subscriber): string
    {
        return $subscriber instanceof PatientAccount ? 'patient' : 'user';
    }
}
