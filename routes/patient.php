<?php

use App\Http\Controllers\Patient\AuthController;
use App\Http\Controllers\Patient\NotificationController;
use App\Http\Controllers\Patient\PortalController;
use App\Http\Controllers\PushController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->name('patient.')->middleware(SetLocale::class)->group(function () {
    Route::post('/language', [PortalController::class, 'setLanguage'])->name('language');

    Route::middleware('guest:patient')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'send'])->middleware('throttle:otp')->name('login.send');
        Route::get('/login/verify', [AuthController::class, 'showVerify'])->name('login.verify');
        Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:otp-verify')->name('login.confirm');
    });

    Route::middleware(['auth:patient', 'no-store'])->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('/appointments', [PortalController::class, 'appointments'])->name('appointments');
        Route::get('/appointments/{id}', [PortalController::class, 'appointment'])->whereNumber('id')->name('appointments.show');
        Route::post('/appointments/{id}/cancel', [PortalController::class, 'cancel'])->whereNumber('id')->name('appointments.cancel');
        Route::post('/appointments/{id}/reschedule', [PortalController::class, 'reschedule'])->whereNumber('id')->name('appointments.reschedule');
        Route::post('/appointments/{id}/pay', [PortalController::class, 'payAppointment'])->whereNumber('id')->name('appointments.pay');
        Route::get('/records', [PortalController::class, 'records'])->name('records');
        Route::get('/prescriptions/{id}/pdf', [PortalController::class, 'prescriptionPdf'])->whereNumber('id')->name('prescriptions.pdf');
        Route::get('/invoices/{id}/pdf', [PortalController::class, 'invoicePdf'])->whereNumber('id')->name('invoices.pdf');
        Route::post('/invoices/{id}/pay', [PortalController::class, 'payInvoice'])->whereNumber('id')->name('invoices.pay');
        Route::get('/reports/{id}', [PortalController::class, 'report'])->whereNumber('id')->name('reports.show');
        Route::get('/consents', [PortalController::class, 'consents'])->name('consents');
        Route::post('/consents/{id}/revoke', [PortalController::class, 'revokeConsent'])->whereNumber('id')->name('consents.revoke');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications', [PushController::class, 'savePreferences'])->name('notifications.save');
        Route::post('/push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:30,1')->name('push.subscribe');
        Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->middleware('throttle:30,1')->name('push.unsubscribe');
        Route::get('/privacy', [PortalController::class, 'privacy'])->name('privacy');
        Route::get('/privacy/download', [PortalController::class, 'downloadData'])->middleware('throttle:10,1')->name('privacy.download');
        Route::post('/privacy/requests', [PortalController::class, 'storeDataRequest'])->middleware('throttle:10,1')->name('privacy.requests');
    });
});
