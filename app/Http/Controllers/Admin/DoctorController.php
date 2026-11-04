<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Services\DoctorVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        return view('admin.doctors.index', [
            'doctors' => Membership::withoutTenancy()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->where('key', 'doctor'))
                ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                ->select('user_id')
                ->distinct()
                ->with(['user:id,name,email,mobile'])
                ->paginate(25)
                ->withQueryString(),
            'profiles' => DoctorProfile::query()
                ->when(in_array($status, ['unverified', 'verified', 'rejected'], true), fn ($query) => $query->where('verification_status', $status))
                ->get()
                ->keyBy('user_id'),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function verify(Request $request, DoctorProfile $profile, DoctorVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'min:5', 'max:500'],
        ], ['reason.required_if' => 'Please say why, so the doctor knows what to fix.']);

        if ($data['status'] === 'rejected') {
            $verification->reject($profile, $request->user('platform'), (string) $data['reason']);

            return back()->with('success', 'Doctor rejected. They have been told why.');
        }

        if (blank($profile->registration_number)) {
            return back()->with('error', 'This doctor has not given a registration number yet.');
        }

        $verification->approve($profile, $request->user('platform'));

        return back()->with('success', 'Doctor verified. Patients can book them now.');
    }
}
