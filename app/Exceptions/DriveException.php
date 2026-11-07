<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Google Drive call that failed. The reason decides what happens next:
 * a refused permission needs the practice to reconnect, a full Drive is skipped,
 * a missing file or folder is reported, anything else is retried on the next run.
 */
class DriveException extends RuntimeException
{
    public const AUTH = 'auth';

    public const FULL = 'full';

    public const NOT_FOUND = 'not_found';

    public const FAILED = 'failed';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
