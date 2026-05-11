<?php

use App\Http\Controllers\Administration\admin_controller;
use App\Http\Controllers\Administration\requests_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'administrator'])->prefix('admin')->group(function () {
    Route::get('/', [admin_controller::class, 'viewAdminPage'])->name('admin.index');

    Route::get('requests', [requests_controller::class, 'viewRequests'])->name('admin.requests.index');
    Route::get('requests/{request}', [requests_controller::class, 'viewRequest'])->name('admin.requests.show');
    Route::patch('requests/{request}/approve', [requests_controller::class, 'approveRequest'])->name('admin.requests.approve');
    Route::patch('requests/{request}/decline', [requests_controller::class, 'declineRequest'])->name('admin.requests.decline');
});
