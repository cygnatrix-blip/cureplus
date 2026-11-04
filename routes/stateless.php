<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CashfreeWebhookController;
use App\Http\Controllers\DocumentLinkController;
use App\Http\Controllers\DomainApprovalController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PlatformWebhookController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\QueueDisplayController;
use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
| No session, no cookies. Browsers and monitors hit these constantly, and a
| session row per hit would fill the sessions table with empty visitors.
*/
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/internal/domains/allowed', DomainApprovalController::class)->name('internal.domains.allowed');
Route::get('/manifests/{portal}.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');

// Outside the web group, so no CSRF: Razorpay cannot send a token. The signature is the check.
// Registered first: "platform" must not be read as an organization's uuid.
Route::post('/webhooks/razorpay/platform', PlatformWebhookController::class)->middleware('throttle:300,1')->name('webhooks.razorpay.platform');
Route::post('/webhooks/razorpay/{organizationUuid}', [RazorpayWebhookController::class, 'handle'])->middleware('throttle:300,1')->name('webhooks.razorpay');
Route::post('/webhooks/cashfree/{organizationUuid}', [CashfreeWebhookController::class, 'handle'])->middleware('throttle:300,1')->name('webhooks.cashfree');
Route::get('/pay/return/cashfree/{organizationUuid}/{linkId}', [CashfreeWebhookController::class, 'returned'])->where('linkId', '[A-Za-z0-9_-]+')->middleware('throttle:60,1')->name('pay.return.cashfree');
Route::get('/pay/callback/{organizationUuid}', [RazorpayWebhookController::class, 'callback'])->middleware('throttle:60,1')->name('pay.callback');

Route::get('/brand/{slug}/manifest.webmanifest', [BrandingController::class, 'manifest'])->middleware('throttle:60,1')->name('brand.manifest');
Route::get('/brand/{slug}/gallery/{index}', [BrandingController::class, 'gallery'])->whereNumber('index')->middleware('throttle:120,1')->name('brand.gallery');
Route::get('/brand/{slug}/doctors/{user}', [BrandingController::class, 'doctor'])->whereNumber('user')->middleware('throttle:120,1')->name('brand.doctor');
Route::get('/brand/{slug}/cover', [BrandingController::class, 'cover'])->middleware('throttle:60,1')->name('brand.cover');
Route::get('/brand/{slug}/icon-{size}.png', [BrandingController::class, 'icon'])->whereNumber('size')->middleware('throttle:60,1')->name('brand.icon');

Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('/files/prescriptions/{prescription}', [DocumentLinkController::class, 'prescription'])->whereNumber('prescription')->name('files.prescription');
    Route::get('/files/invoices/{invoice}', [DocumentLinkController::class, 'invoice'])->whereNumber('invoice')->name('files.invoice');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/display/{token}', [QueueDisplayController::class, 'show'])->name('display.show');
    Route::get('/display/{token}/data', [QueueDisplayController::class, 'data'])->name('display.data');
    Route::get('/display/{token}/manifest.webmanifest', [QueueDisplayController::class, 'manifest'])->name('display.manifest');
});
