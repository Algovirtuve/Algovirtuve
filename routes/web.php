<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::inertia('/', 'dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
