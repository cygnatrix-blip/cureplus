<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Exceptions\ChatClosedException;
use App\Exceptions\ChatLimitException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Services\AppointmentChat;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The doctor's side of the chat. Every action is limited to the appointment's own doctor by AppointmentPolicy::chat.
 */
class AppointmentChatController extends Controller
{
    public function __construct(private readonly AppointmentChat $chat) {}

    /**
     * The doctor's open chats, with unread ones first.
     */
    public function index(Request $request): View
    {
        $appointments = Appointment::query()
            ->where('doctor_user_id', $request->user()->id)
            ->whereNull('chat_closed_at')
            ->whereHas('messages')
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_type', AppointmentMessage::FROM_PATIENT)->whereNull('read_at')])
            ->withMax('messages as last_message_id', 'id')
            ->with('patient:id,name,patient_code')
            ->orderByDesc('unread_count')
            ->orderByDesc('last_message_id')
            ->limit(100)
            ->get();

        return view('app.chats.index', ['appointments' => $appointments]);
    }

    public function show(Appointment $appointment): View
    {
        $this->chat->markRead($appointment, AppointmentMessage::FROM_DOCTOR);
        $appointment->load('patient:id,name,patient_code');

        return view('app.chats.show', [
            'appointment' => $appointment,
            'messages' => $this->chat->messages($appointment),
            'canClose' => $appointment->isChatOpen(),
            'isFinished' => in_array($appointment->status, [AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true),
        ]);
    }

    /**
     * The message list alone, for the page to refresh without a reload.
     */
    public function messages(Appointment $appointment): View
    {
        $this->chat->markRead($appointment, AppointmentMessage::FROM_DOCTOR);

        return view('chat.messages', ['messages' => $this->chat->messages($appointment), 'viewer' => AppointmentMessage::FROM_DOCTOR, 'appointment' => $appointment]);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate(AppointmentMessage::rules(), AppointmentMessage::messages());

        try {
            $this->chat->send($appointment, AppointmentMessage::FROM_DOCTOR, $data['body'] ?? null, $request->file('file'), $request->user());
        } catch (ChatClosedException|ChatLimitException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back();
    }

    public function close(Request $request, Appointment $appointment, AuditLogger $audit): RedirectResponse
    {
        $this->chat->close($appointment, $request->user());
        $audit->record('appointments.chat_closed', $appointment);

        return back()->with('success', 'Chat closed. You and the patient can still read it.');
    }

    public function reopen(Appointment $appointment, AuditLogger $audit): RedirectResponse
    {
        $this->chat->reopen($appointment);
        $audit->record('appointments.chat_reopened', $appointment);

        return back()->with('success', 'Chat reopened.');
    }

    public function file(Appointment $appointment, AppointmentMessage $message, AuditLogger $audit): StreamedResponse
    {
        abort_unless((int) $message->appointment_id === $appointment->id && $message->hasAttachment(), 404);
        $audit->record('appointment_messages.file_viewed', $message);

        return self::stream($message);
    }

    /**
     * Images open in the page; anything else downloads. nosniff stops a browser from treating a file as something else.
     */
    public static function stream(AppointmentMessage $message): StreamedResponse
    {
        return Storage::disk((string) $message->attachment_disk)->response(
            (string) $message->attachment_path,
            (string) $message->attachment_name,
            ['Content-Type' => (string) $message->attachment_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            $message->isImage() ? 'inline' : 'attachment',
        );
    }
}
