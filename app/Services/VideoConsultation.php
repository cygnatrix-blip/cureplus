<?php

namespace App\Services;

use App\Models\DoctorProfile;
use Illuminate\Support\Str;

/**
 * Where a video appointment takes place: the doctor's own Google Meet link if they saved one,
 * otherwise a private Jitsi room. Both are free and open in their own tab or app, because
 * Jitsi's free server does not allow being embedded in another site.
 */
class VideoConsultation
{
    public const GOOGLE_MEET_PATTERN = '#^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$#';

    public function offeredBy(int $doctorId): bool
    {
        return (bool) DoctorProfile::query()->where('user_id', $doctorId)->value('offers_video');
    }

    /**
     * The random name is what keeps a Jitsi room private, as anyone with the link can enter.
     */
    public function linkFor(int $doctorId): string
    {
        $ownLink = DoctorProfile::query()->where('user_id', $doctorId)->value('video_link');

        return $ownLink ?: rtrim((string) config('cureplus.video.jitsi_url'), '/').'/CurePlus'.Str::random(32);
    }

    /**
     * Doctors paste these from the Meet page, which often adds tracking after the code.
     */
    public static function cleanMeetLink(?string $link): ?string
    {
        $link = mb_strtolower(trim((string) $link));

        return $link === '' ? null : (string) strtok($link, '?#');
    }
}
