<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Services\BrandingService;
use App\Services\DoctorVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorProfileController extends Controller
{
    public function update(Request $request, DoctorVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_council' => ['nullable', 'string', 'max:120'],
        ]);

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
