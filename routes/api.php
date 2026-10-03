<?php

use Illuminate\Support\Facades\Route;
use RCI\MemberEngagement\Http\Controllers\Api\ActivityApiController;
use RCI\MemberEngagement\Http\Controllers\Api\AggregationApiController;
use RCI\MemberEngagement\Http\Controllers\Api\AlertsApiController;

Route::middleware(['api', 'auth:api'])
    ->prefix('member-engagement')
    ->name('member-engagement.api.')
    ->group(function () {
        // Activities
        Route::get('/activities', [ActivityApiController::class, 'index'])
            ->name('activities.index');
        Route::get('/activities/{id}', [ActivityApiController::class, 'show'])
            ->name('activities.show');

        // Aggregations (User)
        Route::get('/aggregation/user', [AggregationApiController::class, 'userAggregation'])
            ->name('aggregation.user');
        Route::get('/aggregation/user/by-type', [AggregationApiController::class, 'userByType'])
            ->name('aggregation.user.by-type');

        // Aggregations (Character)
        Route::get('/aggregation/character/{characterId}', [AggregationApiController::class, 'characterAggregation'])
            ->name('aggregation.character');

        // Aggregations (Director)
        Route::middleware('can:view_all_activities')
            ->group(function () {
                Route::get('/aggregation/corporation', [AggregationApiController::class, 'corporationAggregation'])
                    ->name('aggregation.corporation');
                Route::get('/league-tables', [AggregationApiController::class, 'leagueTable'])
                    ->name('league-tables');
            });

        // Alerts
        Route::get('/alerts', [AlertsApiController::class, 'index'])
            ->name('alerts.index');
        Route::post('/alerts', [AlertsApiController::class, 'store'])
            ->name('alerts.store');
        Route::get('/alerts/{alertId}', [AlertsApiController::class, 'show'])
            ->name('alerts.show');
        Route::put('/alerts/{alertId}', [AlertsApiController::class, 'update'])
            ->name('alerts.update');
        Route::delete('/alerts/{alertId}', [AlertsApiController::class, 'destroy'])
            ->name('alerts.destroy');
        Route::post('/alerts/{alertId}/test', [AlertsApiController::class, 'testAlert'])
            ->name('alerts.test');
    });
