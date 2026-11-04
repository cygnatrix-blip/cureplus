<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/privacy', [LandingController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LandingController::class, 'terms'])->name('terms');
Route::view('/offline', 'public.offline')->name('offline');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/login/otp', [OtpLoginController::class, 'show'])->name('login.otp');
    Route::post('/login/otp', [OtpLoginController::class, 'send'])->middleware('throttle:otp')->name('login.otp.send');
    Route::get('/login/otp/verify', [OtpLoginController::class, 'showVerify'])->name('login.otp.verify');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])->middleware('throttle:otp-verify')->name('login.otp.confirm');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:otp')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:login')->name('password.update');
});

Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:otp-verify')->name('two-factor.verify');
Route::post('/two-factor/resend', [TwoFactorChallengeController::class, 'resend'])->middleware('throttle:otp')->name('two-factor.resend');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['auth:web', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])->middleware('throttle:30,1')->name('invitations.show');
Route::post('/invitations/{token}', [InvitationAcceptController::class, 'accept'])->middleware('throttle:register')->name('invitations.accept');

Route::view('/display', 'portals.display')->name('display.home');
