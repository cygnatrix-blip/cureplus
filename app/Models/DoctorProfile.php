<?php

namespace App\Models;

use App\Support\Specialties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $verified_at
 * @property string|null $photo_path
 * @property string|null $verification_note
 */
class DoctorProfile extends Model
{
    protected $fillable = ['user_id', 'qualification', 'specialty', 'offers_video', 'video_link', 'registration_number', 'registration_council'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'offers_video' => 'boolean'];
    }

    public function isAllied(): bool
    {
        return Specialties::isAllied($this->specialty);
    }

    /**
     * What our team needs to check. Allied health professionals may not have a registration
     * number yet, so a degree or membership certificate is accepted from them instead.
     */
    public function hasCredentials(): bool
    {
        if (filled($this->registration_number) && filled($this->registration_council)) {
            return true;
        }

        return $this->isAllied() && $this->certificate() !== null;
    }

    public function certificate(): ?OrganizationDocument
    {
        return OrganizationDocument::withoutTenancy()
            ->where('type', OrganizationDocument::PROFESSIONAL_CERTIFICATE)
            ->where('uploaded_by', $this->user_id)
            ->latest('id')
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
