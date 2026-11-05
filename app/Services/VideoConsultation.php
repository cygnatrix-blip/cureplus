<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\DoctorProfile;

/**
 * Video calls take place on the doctor's own Google Meet link, which opens in its own tab or
 * app. A doctor can only be booked for video once they have turned it on and saved a link.
 */
class VideoConsultation
{
    public const GOOGLE_MEET_PATTERN = '#^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$#';

    public function offeredBy(int $doctorId): bool
    {
        return DoctorProfile::query()->where('user_id', $doctorId)->where('offers_video', true)->whereNotNull('video_link')->exists();
    }

    /**
     * @throws SlotUnavailableException if the doctor removed their link after the patient chose video
     */
    public function linkFor(int $doctorId): string
    {
        return DoctorProfile::query()->where('user_id', $doctorId)->where('offers_video', true)->value('video_link')
            ?? throw new SlotUnavailableException;
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
