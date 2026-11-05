<?php

namespace App\Http\Controllers\App;

use App\Enums\OrganizationStatus;
use App\Exceptions\VerificationIncompleteException;
use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\OrganizationDocument;
use App\Services\AuditLogger;
use App\Services\DemoPatients;
use App\Services\DoctorVerification;
use App\Services\OrganizationVerification;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VerificationController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly OrganizationVerification $verification) {}

    public function index(Request $request): View
    {
        $organization = $this->tenant->organization();

        return view('app.verification', [
            'organization' => $organization,
            'requirements' => $this->verification->requirements($organization),
            'documents' => OrganizationDocument::query()->latest('id')->get(),
            'documentTypes' => OrganizationDocument::TYPES,
            'isDoctor' => $this->tenant->membership()?->hasRole('doctor') ?? false,
            'profile' => DoctorProfile::query()->where('user_id', $request->user()->id)->first(),
            'councils' => config('cureplus.medical_councils'),
        ]);
    }

    public function saveRegistration(Request $request, AuditLogger $audit, DoctorVerification $doctors): RedirectResponse
    {
        abort_unless($this->tenant->membership()?->hasRole('doctor'), 403);

        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:60'],
            'registration_council' => ['required', 'string', 'max:120'],
        ]);

        $profile = DoctorProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
        $changed = $profile->registration_number !== $data['registration_number'] || $profile->registration_council !== $data['registration_council'];
        $profile->fill($data);

        if ($changed) {
            $profile->forceFill(['verification_status' => 'unverified', 'verified_at' => null, 'verified_by' => null]);
        }

        $profile->save();
        $doctors->registrationSaved($profile->load('user:id,name'), $changed);
        $audit->record('organizations.registration_saved', $this->tenant->organization());

        return back()->with('success', 'Registration details saved.');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(array_keys(OrganizationDocument::TYPES))],
            // Checked from the file's contents, not its name. No SVG or HTML: they can carry script.
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'document.mimes' => 'Please send a PDF, JPG or PNG file.',
            'document.max' => 'Please send a file smaller than 5 MB.',
            'document.uploaded' => 'This file is too large for the server to accept. Please send a smaller one.',
        ]);

        $this->verification->storeDocument($this->tenant->organization(), $request->file('document'), (string) $request->input('type'), $request->user()->id);

        return back()->with('success', 'Document uploaded.');
    }

    public function showDocument(OrganizationDocument $document): Response
    {
        return self::documentResponse($document);
    }

    /**
     * Once approved, a document is the record of what was reviewed, so it stays.
     */
    public function destroyDocument(OrganizationDocument $document): RedirectResponse
    {
        if ($this->tenant->organization()->isActive()) {
            return back()->with('error', 'Documents cannot be removed after approval. Please contact support.');
        }

        Storage::disk(config('filesystems.default'))->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    public function resubmit(): RedirectResponse
    {
        $organization = $this->tenant->organization();

        abort_unless($organization->status === OrganizationStatus::Rejected, 404);

        try {
            $this->verification->resubmit($organization);
        } catch (VerificationIncompleteException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Sent for review again. We will email you the result.');
    }

    public function sampleData(DemoPatients $demoPatients): RedirectResponse
    {
        abort_if($this->tenant->organization()->isActive(), 404);

        $demoPatients->create((int) $this->tenant->id());

        return redirect()->route('app.patients.index')->with('success', 'Sample patients added. They are removed automatically when you are approved.');
    }

    /**
     * Sent with its stored type, never as HTML, and with a sandbox so nothing in it can run.
     */
    public static function documentResponse(OrganizationDocument $document): Response
    {
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($disk->exists($document->path), 404);

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $document->original_name);

        return response((string) $disk->get($document->path), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => ($document->isImage() ? 'inline' : 'attachment').'; filename="'.$safeName.'"',
        ]);
    }
}
