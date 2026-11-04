<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\OrganizationDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Proof sent for verification. Stored privately and never linked publicly.
 *
 * @property string $type
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 */
class OrganizationDocument extends Model
{
    /** @use HasFactory<OrganizationDocumentFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    public const TYPES = [
        'doctor_registration' => 'Doctor registration certificate',
        'clinic_proof' => 'Clinic proof (licence, rent agreement, GST certificate or utility bill)',
        'other' => 'Other supporting document',
    ];

    protected $fillable = ['organization_id', 'type', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Document';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
