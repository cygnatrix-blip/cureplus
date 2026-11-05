<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Models\OrganizationDocument;
use App\Services\AuditLogger;
use App\Services\BrandingService;
use App\Services\DoctorVerification;
use App\Services\OrganizationVerification;
use App\Services\VideoConsultation;
use App\Support\Specialties;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorProfileController extends Controller
{
    public function update(Request $request, DoctorVerification $verification): RedirectResponse
    {
        $current = DoctorProfile::query()->where('user_id', $request->user()->id)->value('specialty');
        $request->merge(['video_link' => VideoConsultation::cleanMeetLink($request->input('video_link'))]);

        $data = $request->validate([
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', Rule::in(Specialties::allowed($current))],
            'video_link' => ['required_if_accepted:offers_video', 'nullable', 'regex:'.VideoConsultation::GOOGLE_MEET_PATTERN],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_council' => ['nullable', 'string', 'max:120'],
        ], [
            'video_link.regex' => 'Please paste a Google Meet link, like https://meet.google.com/abc-defg-hij.',
            'video_link.required_if_accepted' => 'Please paste your Google Meet link to turn on video calls.',
        ]);

        $data['offers_video'] = $request->boolean('offers_video');

        $profile = DoctorProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
        $changed = $profile->registration_number !== ($data['registration_number'] ?? null)
            || $profile->registration_council !== ($data['registration_council'] ?? null);
        $profile->fill($data);

        // A verified badge covers one registration. Changing it has to be re-checked.
        if ($changed) {
            $profile->forceFill(['verification_status' => 'unverified', 'verified_at' => null, 'verified_by' => null]);
        }

        $profile->save();
        $verification->registrationSaved($profile->load('user:id,name'), $changed);

        return back()->with('success', 'Doctor profile saved.');
    }

    public function certificate(Request $request, OrganizationVerification $documents, DoctorVerification $verification, AuditLogger $audit): RedirectResponse
    {
        $profile = DoctorProfile::query()->where('user_id', $request->user()->id)->first();

        $membership = Membership::withoutTenancy()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
            ->with('organization')
            ->first();

        abort_unless($profile?->isAllied() && $membership?->organization !== null, 403);

        $request->validate(['certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']], [
            'certificate.mimes' => 'Please attach a PDF, JPG or PNG file.',
            'certificate.max' => 'Please attach a file smaller than 5 MB.',
        ]);

        $document = $documents->storeDocument($membership->organization, $request->file('certificate'), OrganizationDocument::PROFESSIONAL_CERTIFICATE, $request->user()->id);
        $audit->record('doctor_profiles.certificate_uploaded', $document);

        if ($profile->verification_status !== 'verified') {
            $verification->registrationSaved($profile->load('user:id,name'), true);
        }

        return back()->with('success', 'Certificate sent to our team.');
    }

    public function photo(Request $request, BrandingService $branding): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120']], [
            'photo.uploaded' => 'This file is too large for the server to accept. Please choose a smaller one.',
            'photo.mimes' => 'Please use a PNG or JPG image.',
        ]);

        $branding->saveDoctorPhoto(DoctorProfile::query()->firstOrCreate(['user_id' => $request->user()->id]), $request->file('photo'));

        return back()->with('success', 'Photo saved.');
    }
}
