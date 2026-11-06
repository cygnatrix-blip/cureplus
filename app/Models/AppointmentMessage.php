<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\AppointmentMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One message in the chat between a patient and their doctor about an appointment.
 * The text is clinical, so it is encrypted at rest, and the file path never leaves the server.
 *
 * @property string $sender_type
 * @property string|null $body
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class AppointmentMessage extends Model
{
    /** @use HasFactory<AppointmentMessageFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    public const FROM_PATIENT = 'patient';

    public const FROM_DOCTOR = 'doctor';

    /** Text and file limits, shared by both sides. */
    public const MAX_BODY = 2000;

    public const MAX_FILE_KB = 10240;

    public const FILE_TYPES = 'pdf,jpg,jpeg,png';

    protected $fillable = [
        'organization_id', 'appointment_id', 'sender_type', 'sender_user_id', 'sender_account_id', 'body',
        'attachment_disk', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'read_at',
    ];

    protected $hidden = ['attachment_path', 'attachment_disk'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'read_at' => 'datetime', 'attachment_size' => 'integer'];
    }

    /**
     * Shared by the patient's and the doctor's side: text, a file, or both.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:file', 'string', 'max:'.self::MAX_BODY],
            'file' => ['nullable', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:'.self::FILE_TYPES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'body.required_without' => __('Write a message or attach a file.'),
            'file.mimes' => __('Send a PDF, JPG or PNG file.'),
            'file.max' => __('The file must be 10 MB or smaller.'),
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function isFromPatient(): bool
    {
        return $this->sender_type === self::FROM_PATIENT;
    }

    public function isImage(): bool
    {
        return in_array($this->attachment_mime, ['image/jpeg', 'image/png'], true);
    }
}
