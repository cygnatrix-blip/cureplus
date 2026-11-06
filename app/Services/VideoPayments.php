<?php

namespace App\Services;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\VisitType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Billing\FeeResolver;
use App\Services\Messaging\AppointmentMessages;
use App\Services\Messaging\Notifier;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\UpiPayments;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Patients who book themselves pay before the booking is confirmed: always for a video call
 * when the clinic has a gateway, and for either kind when the clinic asks for UPI first.
 * The time is held for them meanwhile, then given back if they do not pay.
 */
class VideoPayments
{
    public const RELEASED_REASON = 'Payment was not received in time.';

    public function __construct(
        private readonly GatewayFactory $gateways,
        private readonly UpiPayments $upi,
        private readonly FeeResolver $fees,
        private readonly AppointmentMessages $messages,
        private readonly DoctorCalendar $calendar,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Null means nothing has to be paid first: no way to pay, not required, or a free consultation.
     */
    public function deadlineFor(int $organizationId, int $doctorId, VisitType $visitType, int $patientId, CarbonImmutable $startsAt, AppointmentMode $mode = AppointmentMode::Online): ?Carbon
    {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return null;
        }

        $mustPayFirst = $this->gateways->forOrganization($organizationId) !== null
            ? $mode === AppointmentMode::Online
            : $this->upi->ruleFor($organizationId, $mode) === 'required';

        if (! $mustPayFirst) {
            return null;
        }

        if ($this->fees->resolve($organization, $doctorId, $visitType, $patientId)['amount_paise'] <= 0) {
            return null;
        }

        $deadline = now()->addMinutes((int) config('cureplus.video.pay_within_minutes'));

        return $deadline->isAfter($startsAt) ? Carbon::instance($startsAt) : $deadline;
    }

    /**
     * Called whenever a bill is paid, online or at the desk.
     */
    public function settled(Invoice $invoice): void
    {
        if ($invoice->status !== 'paid' || $invoice->appointment_id === null) {
            return;
        }

        $appointment = Appointment::withoutTenancy()->find($invoice->appointment_id);

        if ($appointment === null) {
            return;
        }

        if ($appointment->isAwaitingPayment()) {
            $this->confirm($appointment);
        } elseif ($appointment->status === AppointmentStatus::Cancelled && $appointment->cancel_reason === self::RELEASED_REASON) {
            $this->restoreOrFlag($appointment);
        }
    }

    public function releaseUnpaid(): int
    {
        $released = 0;

        $expired = Appointment::withoutTenancy()
            ->where('status', AppointmentStatus::Booked)
            ->whereNotNull('pay_by')
            ->where('pay_by', '<', now())
            ->limit(200)
            ->get();

        foreach ($expired as $appointment) {
            // A payment may have landed between the deadline and this run.
            if (Invoice::withoutTenancy()->where('appointment_id', $appointment->id)->where('status', 'paid')->exists()) {
                $this->confirm($appointment);

                continue;
            }

            // The patient sent a UPI transaction ID in time, so the time stays held while staff check it.
            if ($this->hasUpiPaymentToCheck($appointment)) {
                continue;
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancel_reason' => self::RELEASED_REASON,
                'cancelled_at' => now(),
                'slot_key' => null,
                'pay_by' => null,
            ]);
            $this->audit->record('appointments.video_released_unpaid', $appointment, organizationId: (int) $appointment->organization_id);
            $this->messages->send($appointment, $appointment->isOnline() ? 'video_payment_missed' : 'appointment_payment_missed');
            $released++;
        }

        return $released;
    }

    private function confirm(Appointment $appointment): void
    {
        $appointment->update(['pay_by' => null]);
        $this->messages->send($appointment, 'appointment_booked');
    }

    /**
     * Payment links can be paid after the deadline. The patient gets their time back if it is
     * still free. If not, the clinic is told, because someone has paid and has no appointment.
     */
    private function restoreOrFlag(Appointment $appointment): void
    {
        try {
            DB::transaction(function () use ($appointment): void {
                $startsAt = CarbonImmutable::parse($appointment->starts_at);
                $endsAt = CarbonImmutable::parse($appointment->ends_at);

                if ($startsAt->isPast() || $this->calendar->hasConflict((int) $appointment->doctor_user_id, $startsAt, $endsAt, $appointment->id)) {
                    throw new SlotUnavailableException;
                }

                $appointment->update([
                    'status' => AppointmentStatus::Booked,
                    'cancel_reason' => null,
                    'cancelled_at' => null,
                    'slot_key' => Appointment::slotKey($startsAt),
                ]);
            });
        } catch (SlotUnavailableException|UniqueConstraintViolationException) {
            $this->audit->record('appointments.video_paid_after_release', $appointment, organizationId: (int) $appointment->organization_id);
            $this->tellOwners($appointment);

            return;
        }

        $this->audit->record('appointments.video_restored_after_payment', $appointment, organizationId: (int) $appointment->organization_id);
        $this->messages->send($appointment, 'appointment_booked');
    }

    private function hasUpiPaymentToCheck(Appointment $appointment): bool
    {
        return Payment::withoutTenancy()
            ->where('status', Payment::UNVERIFIED)
            ->whereIn('invoice_id', Invoice::withoutTenancy()->where('appointment_id', $appointment->id)->select('id'))
            ->exists();
    }

    private function tellOwners(Appointment $appointment): void
    {
        $organization = Organization::query()->find($appointment->organization_id);

        $owners = Membership::withoutTenancy()
            ->where('organization_id', $appointment->organization_id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('key', 'owner'))
            ->with('user')
            ->get();

        foreach ($owners as $owner) {
            if ($owner->user !== null) {
                $this->notifier->notifyAccountOwner($owner->user, 'video_refund_needed', [
                    'clinic' => (string) $organization?->name,
                    'time' => (string) $appointment->starts_at?->format('d M, h:i A'),
                ], "video-refund:{$appointment->id}:{$owner->user_id}");
            }
        }
    }
}
