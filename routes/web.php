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

        Route::get('/director/bucket-assets/{characterId}/{bucket}', [DirectorController::class, 'bucketAssets'])
            ->whereNumber('characterId')
            ->where('bucket', '[a-z_0-9]+')
            ->name('director.bucket-assets');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings');
        Route::post('/settings', [SettingsController::class, 'store'])
            ->name('settings.store');
        Route::post('/settings/regions/{list}/add', [SettingsController::class, 'addRegion'])
            ->where('list', 'home|deployment')
            ->name('settings.regions.add');
        Route::post('/settings/regions/{list}/remove', [SettingsController::class, 'removeRegion'])
            ->where('list', 'home|deployment')
            ->name('settings.regions.remove');
    });
