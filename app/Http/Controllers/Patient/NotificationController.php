<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\Messaging\Notifier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, Notifier $notifier): View
    {
        return view('patient.notifications', ['muted' => $notifier->mutedEvents($request->user('patient'))]);
    }
}
