{{-- The treating doctor's way into the call. It opens in its own tab, so the consultation page stays open for notes. --}}
@if ($appointment->canJoinVideo() && (int) $appointment->doctor_user_id === (int) auth('web')->id())
    <a class="btn btn-sm btn-primary" href="{{ $appointment->video_link }}" target="_blank" rel="noopener noreferrer" title="Opens Google Meet. Use the Google account that created your link, and admit only this patient."><x-icon name="video" class="h-4 w-4" />Join video</a>
@endif
