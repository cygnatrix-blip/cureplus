<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserSessions
{
    /**
     * Session ids are bearer credentials, so pages only ever see a hash of them.
     *
     * @return Collection<int, covariant object>
     */
    public function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session) => (object) [
                'token' => hash('sha256', $session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity,
                'is_current' => $session->id === $currentSessionId,
            ]);
    }

    public function revoke(User $user, string $token): bool
    {
        foreach (DB::table('sessions')->where('user_id', $user->id)->pluck('id') as $id) {
            if (hash_equals(hash('sha256', $id), $token)) {
                return DB::table('sessions')->where('id', $id)->delete() > 0;
            }
        }

        return false;
    }

    public function revokeAll(User $user, ?string $exceptSessionId = null): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
