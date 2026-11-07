<?php

namespace App\Services\Storage;

use App\Exceptions\DriveException;
use App\Models\AppointmentMessage;
use App\Models\DriveAccount;
use App\Models\Organization;
use App\Models\VisitAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Copies patient files into the practice's own Google Drive and serves them from wherever they are.
 *
 * Every file is saved on the server first, so uploads never wait for Google. A copy goes to Drive
 * in the background. The server copy stays until a platform admin removes it, which they do only
 * after the practice has agreed, and only once Google confirms it holds identical contents.
 */
class DriveFiles
{
    public const KEEP = 'keep';

    public const REMOVE = 'delete';

    /** What practices can be told about each choice. */
    public const CHOICES = [
        self::KEEP => 'Keep a copy on CurePlus servers (paid)',
        self::REMOVE => 'Remove server copies; Google Drive holds the files',
    ];

    /** Scheduler runs share one PHP process, so each pass stops after this long. */
    private const SECONDS_PER_RUN = 40;

    /**
     * Column names for each kind of patient file.
     *
     * @var array<class-string<VisitAttachment|AppointmentMessage>, array{disk: string, path: string, name: string, mime: string, size: string, label: string}>
     */
    private const SOURCES = [
        VisitAttachment::class => ['disk' => 'disk', 'path' => 'path', 'name' => 'original_name', 'mime' => 'mime_type', 'size' => 'size_bytes', 'label' => 'Visit'],
        AppointmentMessage::class => ['disk' => 'attachment_disk', 'path' => 'attachment_path', 'name' => 'attachment_name', 'mime' => 'attachment_mime', 'size' => 'attachment_size', 'label' => 'Chat'],
    ];

    public function __construct(private readonly GoogleDrive $drive) {}

    /**
     * Copies waiting files of every practice with a connected Drive. Returns how many were copied.
     */
    public function syncPending(int $limit = 30): int
    {
        if (! $this->drive->isConfigured()) {
            return 0;
        }

        $startedAt = microtime(true);
        $copied = 0;
        $organizationIds = DriveAccount::withoutTenancy()->where('status', DriveAccount::ACTIVE)->distinct()->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            foreach (array_keys(self::SOURCES) as $class) {
                foreach ($this->waiting($class, (int) $organizationId)->orderBy('id')->limit($limit - $copied)->get() as $file) {
                    if ($copied >= $limit || microtime(true) - $startedAt > self::SECONDS_PER_RUN) {
                        return $copied;
                    }

                    $outcome = $this->copy($file);

                    if ($outcome === null) {
                        // No Drive of this practice can take files right now; try again on a later run.
                        continue 3;
                    }

                    $copied += (int) $outcome;
                }
            }
        }

        return $copied;
    }

    /**
     * Sends the file from wherever it is kept. Images can open in the page; everything else downloads.
     *
     * @param  array<string, string>  $headers
     */
    public function serve(VisitAttachment|AppointmentMessage $file, bool $inline = false, array $headers = []): StreamedResponse
    {
        $columns = self::SOURCES[$file::class];
        $name = (string) $file->getAttribute($columns['name']);
        $headers = ['Content-Type' => (string) $file->getAttribute($columns['mime']), 'X-Content-Type-Options' => 'nosniff'] + $headers;
        $disposition = $inline ? 'inline' : 'attachment';

        if ($file->getAttribute('server_removed_at') === null) {
            return Storage::disk((string) $file->getAttribute($columns['disk']))->response((string) $file->getAttribute($columns['path']), $name, $headers, $disposition);
        }

        $account = DriveAccount::withoutTenancy()->find($file->getAttribute('drive_account_id'));

        try {
            abort_if($account === null, 404);
            $contents = $this->drive->download($account, (string) $file->getAttribute('drive_file_id'));
        } catch (DriveException $exception) {
            abort($exception->reason === DriveException::NOT_FOUND ? 404 : 503, $exception->reason === DriveException::NOT_FOUND
                ? 'This file is no longer in the practice\'s Google Drive.'
                : 'The practice\'s Google Drive cannot be reached right now. Please try again later.');
        }

        return new StreamedResponse(function () use ($contents): void {
            echo $contents;
        }, 200, $headers + [
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $name, Str::ascii($name) ?: 'file'),
        ]);
    }

    /**
     * Removes server copies for a practice that chose to rely on Google Drive.
     * Each file is checked at Google first, so a file is never left with no copy anywhere.
     *
     * @return int how many files are still left to do
     */
    public function removeServerCopies(Organization $organization, int $limit = 50): int
    {
        if ($organization->drive_server_copy !== self::REMOVE) {
            return 0;
        }

        foreach (array_keys(self::SOURCES) as $class) {
            foreach ($this->onServerAndDrive($class, $organization->id)->orderBy('id')->limit($limit)->get() as $file) {
                $columns = self::SOURCES[$class];
                $disk = Storage::disk((string) $file->getAttribute($columns['disk']));
                $path = (string) $file->getAttribute($columns['path']);
                $account = DriveAccount::withoutTenancy()->findOrFail($file->getAttribute('drive_account_id'));

                try {
                    $safe = $disk->exists($path) && $this->drive->holds($account, (string) $file->getAttribute('drive_file_id'), md5((string) $disk->get($path)));
                } catch (DriveException) {
                    return $this->leftToRemove($organization->id);
                }

                if (! $safe) {
                    // Drive no longer has it, so it is copied again on the next run and the server copy stays.
                    $this->mark($file, ['drive_account_id' => null, 'drive_file_id' => null, 'drive_synced_at' => null]);

                    continue;
                }

                $disk->delete($path);
                $this->mark($file, ['server_removed_at' => now()]);
            }
        }

        return $this->leftToRemove($organization->id);
    }

    /**
     * Brings files back from Google Drive, for a practice that wants the server copy again or is leaving Drive.
     *
     * @return int how many files are still left to do
     */
    public function restoreToServer(Organization $organization, int $limit = 50): int
    {
        foreach (array_keys(self::SOURCES) as $class) {
            foreach ($this->onlyInDrive($class, $organization->id)->orderBy('id')->limit($limit)->get() as $file) {
                $columns = self::SOURCES[$class];
                $account = DriveAccount::withoutTenancy()->findOrFail($file->getAttribute('drive_account_id'));

                try {
                    $contents = $this->drive->download($account, (string) $file->getAttribute('drive_file_id'));
                } catch (DriveException $exception) {
                    if ($exception->reason === DriveException::NOT_FOUND) {
                        Log::warning('A patient file is missing from Google Drive.', ['table' => $file->getTable(), 'id' => $file->getKey(), 'organization_id' => $organization->id]);

                        continue;
                    }

                    return $this->onlyInDriveCount($organization->id);
                }

                Storage::disk((string) $file->getAttribute($columns['disk']))->put((string) $file->getAttribute($columns['path']), $contents);
                $this->mark($file, ['server_removed_at' => null]);
            }
        }

        return $this->onlyInDriveCount($organization->id);
    }

    /**
     * @return array{waiting: int, synced: int, server_bytes: int, only_in_drive: int}
     */
    public function summary(int $organizationId): array
    {
        $summary = ['waiting' => 0, 'synced' => 0, 'server_bytes' => 0, 'only_in_drive' => 0];

        foreach (self::SOURCES as $class => $columns) {
            $summary['waiting'] += $this->waiting($class, $organizationId)->count();
            $summary['synced'] += $this->files($class, $organizationId)->whereNotNull('drive_file_id')->count();
            $summary['server_bytes'] += (int) $this->onServerAndDrive($class, $organizationId, activeOnly: false)->sum($columns['size']);
            $summary['only_in_drive'] += $this->onlyInDrive($class, $organizationId)->count();
        }

        return $summary;
    }

    /**
     * Files in this account that exist nowhere else, so disconnecting it would lose them.
     */
    public function onlyIn(DriveAccount $account): int
    {
        $count = 0;

        foreach (array_keys(self::SOURCES) as $class) {
            $count += $this->onlyInDrive($class, (int) $account->organization_id)->where('drive_account_id', $account->id)->count();
        }

        return $count;
    }

    public function onlyInDriveCount(int $organizationId): int
    {
        $count = 0;

        foreach (array_keys(self::SOURCES) as $class) {
            $count += $this->onlyInDrive($class, $organizationId)->count();
        }

        return $count;
    }

    public function leftToRemove(int $organizationId): int
    {
        $count = 0;

        foreach (array_keys(self::SOURCES) as $class) {
            $count += $this->onServerAndDrive($class, $organizationId)->count();
        }

        return $count;
    }

    /**
     * @return bool|null true when copied, false when this file was skipped, null when no Drive can take it
     */
    private function copy(VisitAttachment|AppointmentMessage $file): ?bool
    {
        $columns = self::SOURCES[$file::class];
        $disk = Storage::disk((string) $file->getAttribute($columns['disk']));
        $path = (string) $file->getAttribute($columns['path']);
        $size = (int) $file->getAttribute($columns['size']);
        $organizationId = (int) $file->getAttribute('organization_id');

        if (! $disk->exists($path)) {
            Log::warning('A patient file is missing from the server, so it cannot be copied to Google Drive.', ['table' => $file->getTable(), 'id' => $file->getKey()]);

            return false;
        }

        $account = $this->accountWithRoom($organizationId, $size);

        if ($account === null) {
            return null;
        }

        $contents = (string) $disk->get($path);
        $name = sprintf('%s %s-%d %s', $file->getAttribute('created_at')?->format('Y-m-d'), $columns['label'], $file->getKey(), $file->getAttribute($columns['name']));

        try {
            $stored = $this->drive->upload($account, $contents, $name, (string) $file->getAttribute($columns['mime']), ['cureplus' => $file->getTable().':'.$file->getKey()]);
        } catch (DriveException $exception) {
            $this->handleFailure($account, $exception);

            return false;
        }

        if (! hash_equals(md5($contents), $stored['md5'])) {
            Log::warning('Google Drive stored different contents, so the copy is not counted.', ['table' => $file->getTable(), 'id' => $file->getKey()]);

            return false;
        }

        $this->mark($file, ['drive_account_id' => $account->id, 'drive_file_id' => $stored['id'], 'drive_synced_at' => now()]);
        $account->forceFill(['quota_used' => $account->quota_used + $size, 'last_error' => null])->save();

        return true;
    }

    /**
     * The connected account with the most free space, checking Google's numbers at most hourly.
     */
    private function accountWithRoom(int $organizationId, int $bytes): ?DriveAccount
    {
        $accounts = DriveAccount::withoutTenancy()->where('organization_id', $organizationId)->where('status', DriveAccount::ACTIVE)->get();

        foreach ($accounts as $account) {
            try {
                if ($account->quota_checked_at === null || $account->quota_checked_at->isBefore(now()->subHour())) {
                    $this->drive->refreshQuota($account);
                }

                if (blank($account->folder_id)) {
                    $account->forceFill(['folder_id' => $this->drive->createFolder($account, $this->folderName($organizationId))])->save();
                }
            } catch (DriveException $exception) {
                $this->handleFailure($account, $exception);
            }
        }

        return $accounts->filter(fn (DriveAccount $account) => $account->isActive() && filled($account->folder_id) && $account->hasRoomFor($bytes))
            ->sortByDesc(fn (DriveAccount $account) => $account->freeBytes() ?? PHP_INT_MAX)
            ->first();
    }

    private function handleFailure(DriveAccount $account, DriveException $exception): void
    {
        match ($exception->reason) {
            DriveException::FULL => $account->forceFill(['quota_used' => (int) $account->quota_total, 'quota_checked_at' => now(), 'last_error' => 'This Google Drive is full.'])->save(),
            // The CurePlus folder was deleted. New files go to a new folder; the next run creates it.
            DriveException::NOT_FOUND => $account->forceFill(['folder_id' => null, 'last_error' => 'The CurePlus folder was deleted from this Drive, so a new one was made.'])->save(),
            DriveException::AUTH => null,
            default => $account->forceFill(['last_error' => Str::limit($exception->getMessage(), 250)])->save(),
        };
    }

    private function folderName(int $organizationId): string
    {
        return 'CurePlus - '.Organization::query()->whereKey($organizationId)->value('name');
    }

    /**
     * A plain query update, so the audit log does not record a storage move as a change to the record.
     *
     * @param  array<string, mixed>  $values
     */
    private function mark(VisitAttachment|AppointmentMessage $file, array $values): void
    {
        $file::withoutTenancy()->whereKey($file->getKey())->update($values);
    }

    /**
     * @param  class-string<VisitAttachment|AppointmentMessage>  $class
     * @return Builder<VisitAttachment>|Builder<AppointmentMessage>
     */
    private function files(string $class, int $organizationId): Builder
    {
        $query = $class::withoutTenancy()->where('organization_id', $organizationId)->whereNotNull(self::SOURCES[$class]['path']);

        if ($class === VisitAttachment::class) {
            // Practice patients are deleted at approval, so their files are never sent anywhere.
            $query->whereIn('visit_id', fn (QueryBuilder $visits) => $visits->select('visits.id')->from('visits')
                ->join('patients', 'patients.id', '=', 'visits.patient_id')->where('patients.is_demo', false));
        }

        return $query;
    }

    /**
     * @param  class-string<VisitAttachment|AppointmentMessage>  $class
     * @return Builder<VisitAttachment>|Builder<AppointmentMessage>
     */
    private function waiting(string $class, int $organizationId): Builder
    {
        return $this->files($class, $organizationId)->whereNull('drive_file_id');
    }

    /**
     * @param  class-string<VisitAttachment|AppointmentMessage>  $class
     * @return Builder<VisitAttachment>|Builder<AppointmentMessage>
     */
    private function onServerAndDrive(string $class, int $organizationId, bool $activeOnly = true): Builder
    {
        $query = $this->files($class, $organizationId)->whereNotNull('drive_file_id')->whereNull('server_removed_at');

        return $activeOnly
            ? $query->whereIn('drive_account_id', DriveAccount::withoutTenancy()->where('organization_id', $organizationId)->where('status', DriveAccount::ACTIVE)->select('id'))
            : $query;
    }

    /**
     * @param  class-string<VisitAttachment|AppointmentMessage>  $class
     * @return Builder<VisitAttachment>|Builder<AppointmentMessage>
     */
    private function onlyInDrive(string $class, int $organizationId): Builder
    {
        return $this->files($class, $organizationId)->whereNotNull('server_removed_at');
    }
}
