<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Public by design: a TV has nobody to log in. It therefore shows only doctor
 * names and token numbers, never a patient's name.
 */
class QueueDisplayController extends Controller
{
    public function show(string $token): View
    {
        return view('portals.display-board', ['location' => $this->location($token), 'token' => $token]);
    }

    public function data(string $token): JsonResponse
    {
        $location = $this->location($token);

        $appointments = Appointment::withoutTenancy()
            ->where('organization_id', $location->organization_id)
            ->where('location_id', $location->id)
            ->whereDate('scheduled_on', today())
            ->whereIn('status', [AppointmentStatus::Booked->value, AppointmentStatus::CheckedIn->value, AppointmentStatus::InConsultation->value])
            ->with('doctor:id,name')
            ->orderBy('token_number')
            ->get(['id', 'doctor_user_id', 'token_number', 'status']);

        return response()->json([
            'doctors' => $appointments->groupBy('doctor_user_id')->map(fn ($rows) => [
                'id' => $rows->first()->doctor_user_id,
                'name' => $rows->first()->doctor?->name,
                'now' => $rows->firstWhere('status', AppointmentStatus::InConsultation)?->token_number,
                'next' => $rows->where('status', AppointmentStatus::CheckedIn)->pluck('token_number')->take(5)->values(),
            ])->values(),
        ]);
    }

    public function manifest(string $token): JsonResponse
    {
        $location = $this->location($token);

        return response()->json([
            'name' => "Queue · {$location->name}",
            'short_name' => 'Queue',
            'id' => "/display/{$token}",
            'start_url' => "/display/{$token}",
            'scope' => '/display',
            'display' => 'fullscreen',
            'background_color' => '#0f172a',
            'theme_color' => '#0f172a',
            'icons' => [
                ['src' => '/icons/display-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/display-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/display-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    private function location(string $token): Location
    {
        return Location::withoutTenancy()->where('display_token', $token)->where('is_active', true)->firstOrFail();
    }
}
