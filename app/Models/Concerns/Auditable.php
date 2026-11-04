<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Writes create, update and delete to the audit log. Only the names of changed
 * fields are stored, so the log never becomes a second copy of personal data.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditLogger::class)->record($model->getTable().'.created', $model));

        static::updated(function ($model): void {
            $fields = array_values(array_diff(array_keys($model->getChanges()), ['updated_at']));

            if ($fields !== []) {
                app(AuditLogger::class)->record($model->getTable().'.updated', $model, ['fields' => $fields]);
            }
        });

        static::deleted(fn ($model) => app(AuditLogger::class)->record($model->getTable().'.deleted', $model));
    }
}
