<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitAttachment extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    protected $fillable = ['organization_id', 'visit_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'category', 'patient_visible', 'uploaded_by'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['patient_visible' => 'boolean', 'size_bytes' => 'integer'];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
