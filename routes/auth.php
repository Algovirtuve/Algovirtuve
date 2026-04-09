<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->controller(AuthController::class)->group(function () {
    Route::get('login', 'createSession')->name('login');
    Route::post('login', 'storeSession')->name('login.store');

    Route::get('register', 'createUser')->name('register');
    Route::post('register', 'storeUser')->name('register.store');

    Route::get('forgot-password', 'createPasswordResetLink')->name('password.request');
    Route::post('forgot-password', 'storePasswordResetLink')->name('password.email');

    Route::get('reset-password/{token}', 'createNewPassword')->name('password.reset');
    Route::post('reset-password', 'storeNewPassword')->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('logout', [AuthController::class, 'destroySession'])->name('logout');
});
