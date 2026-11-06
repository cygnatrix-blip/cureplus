<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\ChatClosedException;
use App\Exceptions\ChatLimitException;
use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\User;
use App\Services\Messaging\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The chat between a patient and their doctor about one appointment. It reads with no tenant
 * scope, because the patient portal has no tenant: callers have already proved access, the
 * portal through PatientRecords and the practice through AppointmentPolicy::chat.
 */
class AppointmentChat
{
    /** Finished appointments close their chat this many days after the appointment date. */
    public const AUTO_CLOSE_DAYS = 30;

    /** Messages shown at once. Older ones stay stored and in the patient's data export. */
    public const SHOWN = 200;

    /** Files each side may send in one chat per day, so uploads cannot fill the server's disk. */
    public const FILES_PER_DAY = 20;

    /** At most one notification per side, per chat, in this many seconds, so a burst of messages is one alert. */
    private const NOTIFY_WINDOW_SECONDS = 600;

    public function __construct(private readonly Notifier $notifier) {}

    /**
     * @return Collection<int, AppointmentMessage>
     */
    public function messages(Appointment $appointment): Collection
    {
        return AppointmentMessage::withoutTenancy()
            ->where('appointment_id', $appointment->id)
            ->latest('id')
            ->limit(self::SHOWN)
            ->get()
            ->reverse()
            ->values()
            ->toBase();
    }

    /**
     * @throws ChatClosedException
     */
    public function send(Appointment $appointment, string $from, ?string $body, ?UploadedFile $file, User|PatientAccount $sender): AppointmentMessage
    {
        if (! $appointment->isChatOpen()) {
            throw new ChatClosedException;
        }

        if ($file !== null && $this->filesSentToday($appointment, $from) >= self::FILES_PER_DAY) {
            throw new ChatLimitException;
        }

        $attachment = $file ? $this->store($file, (int) $appointment->organization_id) : [];

        $message = AppointmentMessage::withoutTenancy()->create([
            'organization_id' => $appointment->organization_id,
            'appointment_id' => $appointment->id,
            'sender_type' => $from,
            'sender_user_id' => $sender instanceof User ? $sender->id : null,
            'sender_account_id' => $sender instanceof PatientAccount ? $sender->id : null,
            'body' => filled($body) ? trim((string) $body) : null,
        ] + $attachment);

        $this->notifyOtherSide($appointment, $from);

        return $message;
    }

    /**
     * Opening the chat marks what the other side sent as read.
     */
    public function markRead(Appointment $appointment, string $reader): void
    {
        AppointmentMessage::withoutTenancy()
            ->where('appointment_id', $appointment->id)
            ->where('sender_type', '!=', $reader)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadFor(Appointment $appointment, string $reader): int
    {
        return AppointmentMessage::withoutTenancy()
            ->where('appointment_id', $appointment->id)
            ->where('sender_type', '!=', $reader)
            ->whereNull('read_at')
            ->count();
    }

    public function close(Appointment $appointment, User $doctor): void
    {
        $appointment->update(['chat_closed_at' => now(), 'chat_closed_by' => $doctor->id]);
    }

    public function reopen(Appointment $appointment): void
    {
        $appointment->update(['chat_closed_at' => null, 'chat_closed_by' => null]);
    }

    /**
     * The safety net for chats a doctor forgets to close. An appointment that has not
     * finished yet keeps its chat, however old it is.
     */
    public function closeStale(): int
    {
        return Appointment::withoutTenancy()
            ->whereNull('chat_closed_at')
            ->whereIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->whereDate('scheduled_on', '<', today()->subDays(self::AUTO_CLOSE_DAYS))
            ->update(['chat_closed_at' => now()]);
    }

    private function filesSentToday(Appointment $appointment, string $from): int
    {
        return AppointmentMessage::withoutTenancy()
            ->where('appointment_id', $appointment->id)
            ->where('sender_type', $from)
            ->whereNotNull('attachment_path')
            ->where('created_at', '>=', today())
            ->count();
    }

    /**
     * The stored name and extension come from us and the detected content, never the patient's filename.
     *
     * @return array{attachment_disk: string, attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int}
     */
    private function store(UploadedFile $file, int $organizationId): array
    {
        $disk = (string) config('filesystems.default');

        return [
            'attachment_disk' => $disk,
            'attachment_path' => (string) $file->storeAs("chat/{$organizationId}", Str::uuid().'.'.$file->extension(), $disk),
            'attachment_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'attachment_mime' => (string) $file->getMimeType(),
            'attachment_size' => (int) $file->getSize(),
        ];
    }

    /**
     * The alert carries no message text, because lock screens show it to anyone nearby.
     */
    private function notifyOtherSide(Appointment $appointment, string $from): void
    {
        $window = intdiv(now()->getTimestamp(), self::NOTIFY_WINDOW_SECONDS);
        $doctor = User::query()->find($appointment->doctor_user_id);

        if ($from === AppointmentMessage::FROM_PATIENT) {
            if ($doctor !== null) {
                $this->notifier->notifyUser($doctor, (int) $appointment->organization_id, 'chat_message_staff', [
                    'time' => $appointment->starts_at?->format('d M, h:i A') ?? $appointment->scheduled_on->format('d M'),
                ], route('app.appointments.chat', $appointment->id), "chat:{$appointment->id}:doctor:{$window}");
            }

            return;
        }

        $patient = Patient::withoutTenancy()->find($appointment->patient_id);

        if ($patient !== null) {
            $this->notifier->notifyPatient($patient, 'chat_message', [
                'doctor' => (string) $doctor?->name,
                'clinic' => (string) Organization::query()->find($appointment->organization_id)?->name,
            ], "chat:{$appointment->id}:patient:{$window}", route('patient.appointments.chat', $appointment->id));
        }
    }
}
