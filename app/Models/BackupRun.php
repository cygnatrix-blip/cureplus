<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $finished_at
 */
class BackupRun extends Model
{
    protected $fillable = ['filename', 'size_bytes', 'checksum', 'tables_count', 'status', 'trigger', 'copied_offsite', 'error', 'finished_at'];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime', 'copied_offsite' => 'boolean', 'size_bytes' => 'integer'];
    }
}
