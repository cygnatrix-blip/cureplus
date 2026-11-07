<?php

namespace App\Services\Storage;

use App\Exceptions\DriveException;
use App\Models\DriveAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Drive through its REST API with Laravel's HTTP client, so no Google library is needed.
 *
 * The drive.file scope only reaches files this app created, never the rest of the practice's Drive,
 * and Google does not require a paid security review for it.
 */
class GoogleDrive
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return route('app.integrations.drive.callback');
    }

    /**
     * prompt=consent makes Google send a refresh token every time, so reconnecting always works.
     */
    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: Carbon}
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->send(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]));

        if (! $response->successful() || blank($response->json('refresh_token'))) {
            throw new DriveException(DriveException::AUTH, 'Google did not give permission.');
        }

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => (string) $response->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ];
    }

    /**
     * Withdraws the permission at Google. Failure is ignored: the practice can also remove it in their Google account.
     */
    public function revoke(DriveAccount $account): void
    {
        if (blank($account->refresh_token)) {
            return;
        }

        try {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $account->refresh_token]);
        } catch (ConnectionException) {
            // Nothing to do: the saved tokens are deleted either way.
        }
    }

    /**
     * Puts the account's email and storage numbers on it, saving them when the account is already saved.
     */
    public function refreshQuota(DriveAccount $account): void
    {
        $response = $this->call($account, fn (PendingRequest $http) => $http->get(self::API.'/about', ['fields' => 'user(emailAddress),storageQuota(limit,usage)']));
        $limit = $response->json('storageQuota.limit');

        $account->forceFill([
            'google_email' => (string) ($response->json('user.emailAddress') ?: $account->google_email),
            'quota_total' => $limit === null ? null : (int) $limit,
            'quota_used' => (int) $response->json('storageQuota.usage', 0),
            'quota_checked_at' => now(),
        ]);

        if ($account->exists) {
            $account->save();
        }
    }

    public function createFolder(DriveAccount $account, string $name): string
    {
        $response = $this->call($account, fn (PendingRequest $http) => $http->post(self::API.'/files?fields=id', [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]));

        return (string) $response->json('id');
    }

    /**
     * A resumable upload, which Google recommends for files over 5 MB.
     *
     * @param  array<string, string>  $properties
     * @return array{id: string, md5: string}
     */
    public function upload(DriveAccount $account, string $contents, string $name, string $mimeType, array $properties = []): array
    {
        $session = $this->call($account, fn (PendingRequest $http) => $http
            ->withHeaders(['X-Upload-Content-Type' => $mimeType, 'X-Upload-Content-Length' => (string) strlen($contents)])
            ->post(self::UPLOAD_API.'/files?uploadType=resumable&fields=id,md5Checksum', [
                'name' => $name,
                'parents' => [(string) $account->folder_id],
                'appProperties' => $properties,
            ]));

        $location = $session->header('Location');

        if ($location === '') {
            throw new DriveException(DriveException::FAILED, 'Google did not start the upload.');
        }

        $response = $this->call($account, fn (PendingRequest $http) => $http->withBody($contents, $mimeType)->timeout(120)->put($location));

        return ['id' => (string) $response->json('id'), 'md5' => (string) $response->json('md5Checksum')];
    }

    public function download(DriveAccount $account, string $fileId): string
    {
        return $this->call($account, fn (PendingRequest $http) => $http->timeout(60)->get(self::API.'/files/'.rawurlencode($fileId), ['alt' => 'media']))->body();
    }

    /**
     * Whether the file is still in Drive, not in the bin, with exactly these contents.
     */
    public function holds(DriveAccount $account, string $fileId, string $md5): bool
    {
        try {
            $response = $this->call($account, fn (PendingRequest $http) => $http->get(self::API.'/files/'.rawurlencode($fileId), ['fields' => 'id,md5Checksum,trashed']));
        } catch (DriveException $exception) {
            if ($exception->reason === DriveException::NOT_FOUND) {
                return false;
            }

            throw $exception;
        }

        return ! $response->json('trashed') && hash_equals($md5, (string) $response->json('md5Checksum'));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function call(DriveAccount $account, callable $request): Response
    {
        $response = $this->send(fn () => $request(Http::withToken($this->accessToken($account))->acceptJson()->timeout(30)));

        // A token can stop working before its expiry time; one fresh token is worth trying before giving up.
        if ($response->status() === 401) {
            $this->persist($account, ['access_token' => null, 'token_expires_at' => null]);
            $response = $this->send(fn () => $request(Http::withToken($this->accessToken($account))->acceptJson()->timeout(30)));
        }

        if ($response->successful()) {
            return $response;
        }

        $reason = (string) $response->json('error.errors.0.reason');

        throw match (true) {
            $response->status() === 401 => $this->refused($account),
            $response->status() === 404 => new DriveException(DriveException::NOT_FOUND, 'Not found in Google Drive.'),
            in_array($reason, ['storageQuotaExceeded', 'quotaExceeded'], true) => new DriveException(DriveException::FULL, 'This Google Drive is full.'),
            default => new DriveException(DriveException::FAILED, 'Google Drive answered '.$response->status().'.'),
        };
    }

    /**
     * Access tokens last an hour; a minute's margin covers slow uploads.
     */
    private function accessToken(DriveAccount $account): string
    {
        if (filled($account->access_token) && $account->token_expires_at?->isAfter(now()->addMinute())) {
            return (string) $account->access_token;
        }

        if (blank($account->refresh_token)) {
            throw $this->refused($account);
        }

        $response = $this->send(fn () => Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]));

        if ($response->json('error') === 'invalid_grant') {
            throw $this->refused($account);
        }

        if (! $response->successful()) {
            throw new DriveException(DriveException::FAILED, 'Google did not renew access.');
        }

        $this->persist($account, [
            'access_token' => (string) $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return (string) $account->access_token;
    }

    /**
     * The practice withdrew access, or changed its Google password. Nothing more is tried until it reconnects.
     */
    private function refused(DriveAccount $account): DriveException
    {
        $this->persist($account, ['status' => DriveAccount::NEEDS_RECONNECT, 'access_token' => null, 'last_error' => 'Google no longer allows access. Connect this account again.']);

        return new DriveException(DriveException::AUTH, 'Google no longer allows access to this Drive.');
    }

    /**
     * An account still being connected is not saved yet; it is saved once Google has named it.
     *
     * @param  array<string, mixed>  $values
     */
    private function persist(DriveAccount $account, array $values): void
    {
        $account->forceFill($values);

        if ($account->exists) {
            $account->save();
        }
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new DriveException(DriveException::FAILED, 'Google Drive could not be reached.');
        }
    }
}
