<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\DriverRideController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'role:rider'])->group(function () {
    Route::post('/rides', [RideController::class, 'store']);
    Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel']);
    Route::get('/rides', [RideController::class, 'index']);
    Route::get('/rides/{ride}', [RideController::class, 'show']);
});

// Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {
//     Route::get('/rides/available', [DriverRideController::class, 'availableRides']);
// });

Route::prefix('driver')->middleware(['auth:sanctum', 'role:driver'])->group(function () {
    Route::get('/rides/available', [DriverRideController::class, 'availableRides']);
    Route::post('/rides/{ride}/accept', [DriverRideController::class, 'acceptRide']);
    Route::post('/rides/{ride}/start', [DriverRideController::class, 'startRide']);
    Route::post('/rides/{ride}/complete', [DriverRideController::class, 'completeRide']);
    Route::get('/rides/current', [DriverRideController::class, 'currentRide']);
});