<?php

namespace App\Services;

use App\Models\Announcement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnouncementFeed
{
    /**
     * @return Collection<int, Announcement>
     */
    public function for(int $organizationId, int $userId): Collection
    {
        $read = DB::table('announcement_reads')->where('user_id', $userId)->pluck('announcement_id');

        return Announcement::query()
            ->live()
            ->whereNotIn('id', $read)
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(fn (Announcement $announcement) => $announcement->isFor($organizationId))
            ->take(3)
            ->values()
            ->toBase();
    }
}
