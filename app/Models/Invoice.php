<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\BelongsToOrganization;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $issued_at
 * @property Carbon|null $cancelled_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'location_id', 'patient_id', 'doctor_user_id', 'appointment_id', 'number', 'kind', 'status',
        'subtotal_paise', 'discount_paise', 'discount_reason', 'exempt_paise', 'taxable_paise', 'tax_paise', 'total_paise',
        'paid_paise', 'refunded_paise', 'gstin_snapshot', 'issued_at', 'cancelled_at', 'cancel_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal_paise' => 'integer',
            'discount_paise' => 'integer',
            'exempt_paise' => 'integer',
            'taxable_paise' => 'integer',
            'tax_paise' => 'integer',
            'total_paise' => 'integer',
            'paid_paise' => 'integer',
            'refunded_paise' => 'integer',
        ];
    }

    public function duePaise(): int
    {
        return $this->status === 'cancelled' ? 0 : max(0, $this->total_paise - $this->paid_paise);
    }

    public function isGstInvoice(): bool
    {
        return $this->kind === 'gst_invoice';
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
