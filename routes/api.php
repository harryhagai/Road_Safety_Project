<?php

use App\Http\Controllers\Api\PassengerTripController;
use Illuminate\Support\Facades\Route;

Route::prefix('trips')
    ->middleware('throttle:180,1')
    ->group(function (): void {
        Route::post('/start', [PassengerTripController::class, 'start']);
        Route::get('/{trip}/status', [PassengerTripController::class, 'status']);
        Route::post('/{trip}/telemetry', [PassengerTripController::class, 'telemetry']);
        Route::post('/{trip}/violations', [PassengerTripController::class, 'violation']);
        Route::post('/{trip}/stop', [PassengerTripController::class, 'stop']);
    });
