<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property Carbon|null $issued_at
 * @property Carbon|null $follow_up_on
 */
class Prescription extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    /** Everything the printed prescription shows, loaded together. */
    public const DOCUMENT_RELATIONS = ['items', 'patient', 'location', 'organization', 'visit.vital'];

    /** The colour used when a practice has not chosen one. */
    private const DEFAULT_COLOUR = '#088575';

    protected $fillable = [
        'organization_id', 'visit_id', 'patient_id', 'location_id', 'doctor_user_id', 'doctor_name', 'doctor_specialty', 'is_teleconsultation', 'doctor_qualification',
        'doctor_registration_number', 'doctor_registration_council', 'lab_advice', 'patient_instructions', 'instructions_language', 'follow_up_on', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'lab_advice' => 'encrypted',
            'patient_instructions' => 'encrypted',
            'is_teleconsultation' => 'boolean',
            'follow_up_on' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PrescriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The reference printed on the prescription, so a pharmacy or the clinic can find it.
     */
    public function number(): string
    {
        return 'RX-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * The practice's own colour, only when it is a plain hex colour, because it is placed inside a style attribute.
     */
    public function accentColour(): string
    {
        $colour = (string) $this->organization?->brand_color;

        return preg_match('/^#[0-9a-fA-F]{6}$/', $colour) === 1 ? $colour : self::DEFAULT_COLOUR;
    }

    /**
     * Whether any dose is written as morning-afternoon-night counts, such as 1-0-1, so the key is printed.
     */
    public function usesDoseCounts(): bool
    {
        return $this->items->contains(fn (PrescriptionItem $item): bool => preg_match('/^\d+(\.\d+)?(\s*-\s*\d+(\.\d+)?){2,3}$/', (string) $item->dosage) === 1);
    }

    /**
     * "34 Y / Female", leaving out whatever is not known.
     */
    public function patientAgeAndSex(): string
    {
        $age = $this->patient->ageInYears();

        return collect([$age === null ? null : "{$age} Y", $this->patient->gender ? ucfirst((string) $this->patient->gender) : null])->filter()->implode(' / ');
    }

    /**
     * The practice logo inlined, so it shows in the PDF and on shared links without a signed image URL.
     */
    public function logoDataUri(): ?string
    {
        $path = $this->organization?->icon_192_path;
        $disk = Storage::disk(config('filesystems.default'));

        return $path && $disk->exists($path) ? 'data:image/png;base64,'.base64_encode((string) $disk->get($path)) : null;
    }
}
