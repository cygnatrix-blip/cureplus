{{-- The treating doctor's way into the call. It opens in its own tab, so the consultation page stays open for notes. --}}
@if ($appointment->canJoinVideo() && (int) $appointment->doctor_user_id === (int) auth('web')->id())
    <a class="btn btn-sm btn-primary" href="{{ $appointment->video_link }}" target="_blank" rel="noopener noreferrer" title="Join before the patient. Jitsi asks you to sign in with Google once."><x-icon name="video" class="h-4 w-4" />Join video</a>
@endif
