<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClinicSiteController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
| Loaded last: these catch first-level paths, so every real route has to win first.
| cureplus.reserved_slugs keeps organizations from claiming a real route's path.
*/
Route::middleware(SetLocale::class)->group(function () {
    Route::get('/book/{slug}', [BookingController::class, 'show'])->where('slug', '[a-z0-9][a-z0-9-]*')->name('booking.show');
    Route::post('/book/{slug}', [BookingController::class, 'choose'])->middleware('throttle:booking')->name('book.choose');
    Route::get('/book/{slug}/details', [BookingController::class, 'details'])->name('book.details');
    Route::post('/book/{slug}/otp', [BookingController::class, 'sendOtp'])->middleware('throttle:otp')->name('book.otp');
    Route::post('/book/{slug}/confirm', [BookingController::class, 'confirm'])->middleware('throttle:booking')->name('book.confirm');

    Route::get('/{slug}', [ClinicSiteController::class, 'show'])->where('slug', '[a-z0-9][a-z0-9-]*')->name('clinic.home');
    Route::get('/{slug}/{doctorSlug}', [BookingController::class, 'show'])->where(['slug' => '[a-z0-9][a-z0-9-]*', 'doctorSlug' => '[a-z0-9][a-z0-9-]*'])->name('booking.doctor');
});
