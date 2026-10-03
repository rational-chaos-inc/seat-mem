<?php

use Illuminate\Support\Facades\Route;
use RCI\MemberEngagement\Http\Controllers\DashboardController;
use RCI\MemberEngagement\Http\Controllers\DirectorController;
use RCI\MemberEngagement\Http\Controllers\SettingsController;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('member-engagement')
    ->name('member-engagement.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'member'])
            ->name('dashboard');

        Route::get('/director', [DirectorController::class, 'index'])
            ->name('director.index');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings');
        Route::post('/settings', [SettingsController::class, 'store'])
            ->name('settings.store');
    });
