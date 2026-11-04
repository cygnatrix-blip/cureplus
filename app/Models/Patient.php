<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date_of_birth
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'patient_account_id', 'patient_code', 'name', 'mobile', 'email', 'date_of_birth', 'dob_is_estimated',
        'gender', 'relation', 'address', 'abha_id', 'abha_hash', 'allergies',
    ];

    protected $hidden = ['abha_id', 'abha_hash', 'allergies'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dob_is_estimated' => 'boolean',
            'abha_id' => 'encrypted',
            'allergies' => 'encrypted',
        ];
    }

    /**
     * Emails link a record to a portal login, so they must compare exactly.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public static function hashAbha(string $abhaId): string
    {
        $digits = preg_replace('/\D+/', '', $abhaId) ?? '';

        return hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    public function ageInYears(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $query->where(function (Builder $query) use ($term, $digits): void {
            $query->where('name', 'like', "%{$term}%")->orWhere('patient_code', $term);

            if (strlen($digits) >= 4) {
                $query->orWhere('mobile', 'like', "%{$digits}%");
            }

            if (strlen($digits) === 14) {
                $query->orWhere('abha_hash', self::hashAbha($digits));
            }
        });
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
