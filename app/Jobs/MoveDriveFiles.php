<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Storage\DriveFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Removes server copies of a practice's Drive files, or brings them back, in batches.
 * Each batch queues the next, so shared hosting never runs one long request.
 */
class MoveDriveFiles implements ShouldQueue
{
    use Queueable;

    public const REMOVE = 'remove';

    public const RESTORE = 'restore';

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly int $organizationId, public readonly string $direction) {}

    public function handle(DriveFiles $files): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $before = $this->direction === self::RESTORE ? $files->onlyInDriveCount($organization->id) : $files->leftToRemove($organization->id);
        $left = $this->direction === self::RESTORE ? $files->restoreToServer($organization) : $files->removeServerCopies($organization);

        // Stops when a batch makes no progress, for example when Google cannot be reached.
        if ($left > 0 && $left < $before && ($this->direction === self::RESTORE || $organization->drive_server_copy === DriveFiles::REMOVE)) {
            self::dispatch($this->organizationId, $this->direction);
        }
    }
}
