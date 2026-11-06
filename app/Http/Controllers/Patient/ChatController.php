<?php

namespace App\Http\Controllers\Patient;

use App\Exceptions\ChatClosedException;
use App\Exceptions\ChatLimitException;
use App\Http\Controllers\App\AppointmentChatController;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentMessage;
use App\Models\PatientAccount;
use App\Services\AppointmentChat;
use App\Services\Portal\PatientRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The patient's side of the chat. Access is proved through PatientRecords, so a patient
 * reaches only the appointments of people linked to their own account.
 */
class ChatController extends Controller
{
    public function __construct(private readonly AppointmentChat $chat, private readonly PatientRecords $records) {}

    public function show(Request $request, int $id): View
    {
        $appointment = $this->appointment($request, $id);
        $this->chat->markRead($appointment, AppointmentMessage::FROM_PATIENT);

        return view('patient.chat', ['appointment' => $appointment, 'messages' => $this->chat->messages($appointment)]);
    }

    public function messages(Request $request, int $id): View
    {
        $appointment = $this->appointment($request, $id);
        $this->chat->markRead($appointment, AppointmentMessage::FROM_PATIENT);

        return view('chat.messages', ['messages' => $this->chat->messages($appointment), 'viewer' => AppointmentMessage::FROM_PATIENT, 'appointment' => $appointment]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $appointment = $this->appointment($request, $id);
        $data = $request->validate(AppointmentMessage::rules(), AppointmentMessage::messages());

        try {
            $this->chat->send($appointment, AppointmentMessage::FROM_PATIENT, $data['body'] ?? null, $request->file('file'), $this->account($request));
        } catch (ChatClosedException|ChatLimitException $exception) {
            return back()->withInput()->with('error', __($exception->getMessage()));
        }

        return back();
    }

    public function file(Request $request, int $id, int $message): StreamedResponse
    {
        $appointment = $this->appointment($request, $id);
        $file = AppointmentMessage::withoutTenancy()->where('appointment_id', $appointment->id)->findOrFail($message);
        abort_unless($file->hasAttachment(), 404);

        return AppointmentChatController::stream($file);
    }

    private function appointment(Request $request, int $id): Appointment
    {
        return $this->records->appointment($this->account($request), $id);
    }

    private function account(Request $request): PatientAccount
    {
        /** @var PatientAccount $account */
        $account = $request->user('patient');

        return $account;
    }
}
