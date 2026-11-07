<?php

namespace App\Http\Controllers\App;

use App\Exceptions\DriveException;
use App\Http\Controllers\Controller;
use App\Models\AppointmentMessage;
use App\Models\DriveAccount;
use App\Models\VisitAttachment;
use App\Services\AuditLogger;
use App\Services\Storage\DriveFiles;
use App\Services\Storage\GoogleDrive;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connects Google accounts so the practice's patient files are also kept in its own Drive.
 */
class DriveController extends Controller
{
    private const STATE_KEY = 'google_drive_state';

    public function __construct(private readonly GoogleDrive $drive, private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    /**
     * A plain link rather than a form, because the CSP's form-action would block the redirect to Google.
     */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->drive->isConfigured()) {
            return to_route('app.integrations.index')->with('error', 'Google Drive is not available yet. Please ask CurePlus support.');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, ['state' => $state, 'organization_id' => $this->tenant->id()]);

        return redirect()->away($this->drive->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        // The state ties Google's answer to this practice and this browser, so a forged link cannot attach someone else's Drive.
        if (! is_array($expected) || ! hash_equals((string) $expected['state'], (string) $request->query('state')) || $expected['organization_id'] !== $this->tenant->id()) {
            return to_route('app.integrations.index')->with('error', 'That Google sign-in could not be matched to this practice. Please try again.');
        }

        if (blank($request->query('code'))) {
            return to_route('app.integrations.index')->with('error', 'Google Drive was not connected.');
        }

        try {
            $tokens = $this->drive->exchangeCode((string) $request->query('code'));
            $account = new DriveAccount(['refresh_token' => $tokens['refresh_token'], 'access_token' => $tokens['access_token'], 'token_expires_at' => $tokens['expires_at']]);
            $this->drive->refreshQuota($account);
        } catch (DriveException) {
            return to_route('app.integrations.index')->with('error', 'Google did not allow access to Drive. Please try again and tick the Drive permission.');
        }

        // Connecting the same Google account again renews its permission instead of adding it twice.
        $saved = DriveAccount::query()->firstOrNew(['google_email' => Str::lower($account->google_email)]);
        $saved->fill([
            'refresh_token' => $account->refresh_token,
            'access_token' => $account->access_token,
            'token_expires_at' => $account->token_expires_at,
            'quota_total' => $account->quota_total,
            'quota_used' => $account->quota_used,
            'quota_checked_at' => now(),
            'status' => DriveAccount::ACTIVE,
            'last_error' => null,
            'connected_by' => $request->user()->id,
        ])->save();

        $this->audit->record('drive_accounts.connected', $saved, ['google_email' => $saved->google_email]);

        return to_route('app.integrations.index')->with('success', "Google Drive {$saved->google_email} connected. Patient files will be copied to it within a few minutes.");
    }

    public function disconnect(DriveAccount $account, DriveFiles $files): RedirectResponse
    {
        $onlyThere = $files->onlyIn($account);

        if ($onlyThere > 0) {
            return back()->with('error', "{$onlyThere} patient files are kept only in this Google Drive. Ask CurePlus support to bring them back to the server before you disconnect it.");
        }

        $this->drive->revoke($account);
        $account->forceFill(['status' => DriveAccount::DISCONNECTED, 'refresh_token' => null, 'access_token' => null, 'token_expires_at' => null])->save();

        // Files copied to this account still have their server copy; they are copied again to another connected Drive.
        foreach ([VisitAttachment::class, AppointmentMessage::class] as $class) {
            $class::query()->where('drive_account_id', $account->id)->whereNull('server_removed_at')->update(['drive_account_id' => null, 'drive_file_id' => null, 'drive_synced_at' => null]);
        }

        $this->audit->record('drive_accounts.disconnected', $account, ['google_email' => $account->google_email]);

        return back()->with('success', "Google Drive {$account->google_email} disconnected. The files already copied stay in that Drive.");
    }
}
