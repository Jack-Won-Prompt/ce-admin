<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');

    Route::get('/login/otp', [AuthController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/otp', [AuthController::class, 'verifyOtp'])->name('login.otp.verify');
    Route::post('/login/otp/resend', [AuthController::class, 'resendOtp'])->name('login.otp.resend');

    Route::get('/auth/sso', [AuthController::class, 'ssoRedirect'])->name('sso.redirect');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
