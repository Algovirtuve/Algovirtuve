<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'createSession'])->name('login');
    Route::post('login', [AuthController::class, 'storeSession'])->name('login.store');

    Route::get('register', [AuthController::class, 'createUser'])->name('register');
    Route::post('register', [AuthController::class, 'storeUser'])->name('register.store');

    Route::get('forgot-password', [AuthController::class, 'createPasswordResetLink'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'storePasswordResetLink'])->name('password.email');

    Route::get('reset-password/{token}', [AuthController::class, 'createNewPassword'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'storeNewPassword'])->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('logout', [AuthController::class, 'destroySession'])->name('logout');
});
