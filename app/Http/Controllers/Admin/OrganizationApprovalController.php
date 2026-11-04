<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Exceptions\VerificationIncompleteException;
use App\Http\Controllers\App\VerificationController;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Services\AuditLogger;
use App\Services\DoctorVerification;
use App\Services\OrganizationVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrganizationApprovalController extends Controller
{
    public function __construct(private readonly OrganizationVerification $verification) {}

    public function index(DoctorVerification $doctors): View
    {
        return view('admin.approvals.index', [
            // Oldest first: whoever has waited longest is reviewed first.
            'pending' => Organization::query()->where('status', OrganizationStatus::PendingVerification)->orderBy('created_at')->paginate(25),
            'rejected' => Organization::query()->where('status', OrganizationStatus::Rejected)->latest('rejected_at')->limit(25)->get(),
            'verification' => $this->verification,
            'doctors' => $doctors->waiting(),
        ]);
    }

    public function show(Organization $organization): View
    {
        $members = Membership::withoutTenancy()
            ->where('organization_id', $organization->id)
            ->whereHas('roles', fn ($query) => $query->where('key', 'owner'))
            ->with(['user:id,name,email,mobile', 'roles:id,key,name'])
            ->get();

        return view('admin.approvals.show', [
            'organization' => $organization,
            'requirements' => $this->verification->requirements($organization),
            'isComplete' => $this->verification->isComplete($organization),
            'owners' => $members,
            'profiles' => DoctorProfile::query()->whereIn('user_id', $members->pluck('user_id'))->get()->keyBy('user_id'),
            'locations' => $organization->locations()->orderBy('name')->limit(50)->get(),
            'documents' => OrganizationDocument::withoutTenancy()->where('organization_id', $organization->id)->latest('id')->get(),
        ]);
    }

    /**
     * Every look at someone's identity documents is on the record.
     */
    public function document(Organization $organization, int $document, AuditLogger $audit): Response
    {
        $record = OrganizationDocument::withoutTenancy()->where('organization_id', $organization->id)->findOrFail($document);
        $audit->record('organizations.document_viewed', $organization, ['document_id' => $record->id]);

        return VerificationController::documentResponse($record);
    }

    public function approve(Request $request, Organization $organization): RedirectResponse
    {
        try {
            $this->verification->approve($organization, $request->user('platform'));
        } catch (VerificationIncompleteException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.approvals.index')->with('success', "{$organization->name} is approved. The owner has been told.");
    }

    public function reject(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->status === OrganizationStatus::PendingVerification, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [
            'reason.required' => 'Please say why, so the practice knows what to fix.',
        ]);

        $this->verification->reject($organization, $request->user('platform'), $data['reason']);

        return redirect()->route('admin.approvals.index')->with('success', "{$organization->name} was rejected. The owner has been told why.");
    }
}
