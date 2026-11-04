<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $detected_at
 * @property Carbon|null $board_notified_at
 * @property Carbon|null $patients_notified_at
 * @property Carbon|null $resolved_at
 */
class BreachIncident extends Model
{
    use Auditable;

    protected $fillable = ['title', 'description', 'severity', 'status', 'affected_count', 'detected_at', 'board_notified_at', 'patients_notified_at', 'resolved_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'board_notified_at' => 'datetime', 'patients_notified_at' => 'datetime', 'resolved_at' => 'datetime', 'description' => 'encrypted', 'notes' => 'encrypted'];
    }
}
