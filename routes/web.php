<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::inertia('/', 'main_page')->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/preferences.php';
require __DIR__.'/recipes.php';
require __DIR__.'/settings.php';
