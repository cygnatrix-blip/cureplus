<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function dismiss(Request $request, Announcement $announcement): RedirectResponse
    {
        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user()->id,
            'read_at' => now(),
        ]);

        return back();
    }
}
