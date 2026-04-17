<?php

use App\Http\Controllers\Authentication_managment\auth_controller;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [auth_controller::class, 'createSession'])->name('login');
    Route::post('login', [auth_controller::class, 'storeSession'])->middleware('throttle:login')->name('login.store');

    Route::get('register', [auth_controller::class, 'createUser'])->name('register');
    Route::post('register', [auth_controller::class, 'storeUser'])->name('register.store');

    Route::get('forgot-password', [auth_controller::class, 'createPasswordResetLink'])->name('password.request');
    Route::post('forgot-password', [auth_controller::class, 'storePasswordResetLink'])->name('password.email');

    Route::get('reset-password/{token}', [auth_controller::class, 'createNewPassword'])->name('password.reset');
    Route::post('reset-password', [auth_controller::class, 'storeNewPassword'])->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('logout', [auth_controller::class, 'destroySession'])->name('logout');
});
