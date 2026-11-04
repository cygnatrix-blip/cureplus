<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_council' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = DoctorProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
        $changedRegistration = $profile->registration_number !== ($data['registration_number'] ?? null);
        $profile->fill($data);

        // A verified badge covers one number. Changing the number has to be re-checked.
        if ($changedRegistration) {
            $profile->forceFill(['verification_status' => 'unverified', 'verified_at' => null, 'verified_by' => null]);
        }

        $profile->save();

        return back()->with('success', 'Doctor profile saved.');
    }
}
