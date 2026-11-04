<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Services\AuditLogger;
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

    public function verify(Request $request, DoctorProfile $profile, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'unverified'])]]);

        $profile->forceFill([
            'verification_status' => $data['status'],
            'verified_at' => $data['status'] === 'verified' ? now() : null,
            'verified_by' => $request->user('platform')->id,
        ])->save();

        $audit->record('doctor_profiles.'.$data['status'], $profile);

        return back()->with('success', 'Doctor updated.');
    }
}
