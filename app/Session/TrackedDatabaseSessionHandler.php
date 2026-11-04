<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;

/**
 * Laravel records the default guard's id in sessions.user_id. With two guards,
 * admin #5 would be saved as "user 5" and show up in clinic user 5's device
 * list, where that user could revoke it. Each guard gets its own column instead.
 */
class TrackedDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function addUserInformation(&$payload)
    {
        $payload['user_id'] = Auth::guard('web')->id();
        $payload['platform_admin_id'] = Auth::guard('platform')->id();

        return $this;
    }
}
