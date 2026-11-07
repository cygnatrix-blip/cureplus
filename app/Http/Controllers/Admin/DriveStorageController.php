<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MoveDriveFiles;
use App\Models\DriveAccount;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Storage\DriveFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Practices that store patient files in their own Google Drive.
 *
 * Server copies are only removed after the practice has agreed, which the admin records here.
 * A practice that wants to keep them pays separately for that space.
 */
class DriveStorageController extends Controller
{
    public function __construct(private readonly DriveFiles $files, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $accounts = DriveAccount::withoutTenancy()->orderBy('google_email')->get()->groupBy('organization_id');
        $organizations = Organization::query()->whereIn('id', $accounts->keys())->orderBy('name')->get();

        return view('admin.drive.index', [
            'rows' => $organizations->map(fn (Organization $organization) => [
                'organization' => $organization,
                'accounts' => $accounts->get($organization->id),
                'summary' => $this->files->summary($organization->id),
            ]),
        ]);
    }

    public function decide(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'choice' => ['required', Rule::in(array_keys(DriveFiles::CHOICES))],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $organization->forceFill(['drive_server_copy' => $data['choice'], 'drive_server_copy_decided_at' => now(), 'drive_server_copy_note' => $data['note']])->save();
        $this->audit->record('organizations.drive_server_copy_decided', $organization, ['choice' => $data['choice'], 'note' => $data['note']], $organization->id);

        return back()->with('success', "Saved for {$organization->name}: ".DriveFiles::CHOICES[$data['choice']].'.');
    }

    public function removeServerCopies(Organization $organization): RedirectResponse
    {
        if ($organization->drive_server_copy !== DriveFiles::REMOVE) {
            return back()->with('error', 'First record that the practice agreed to remove the server copies.');
        }

        MoveDriveFiles::dispatch($organization->id, MoveDriveFiles::REMOVE);
        $this->audit->record('organizations.drive_server_copies_removed', $organization, [], $organization->id);

        return back()->with('success', 'Removing server copies. Each file is checked in Google Drive first, so this takes a few minutes.');
    }

    public function restore(Organization $organization): RedirectResponse
    {
        MoveDriveFiles::dispatch($organization->id, MoveDriveFiles::RESTORE);
        $this->audit->record('organizations.drive_files_restored', $organization, [], $organization->id);

        return back()->with('success', 'Bringing files back from Google Drive to the server. This takes a few minutes.');
    }
}
