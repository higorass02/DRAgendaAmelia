<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\ProfessionalController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'ok';
    } catch (\Throwable) {
        $database = 'unavailable';
    }

    return response()->json([
        'status' => $database === 'ok' ? 'ok' : 'degraded',
        'database' => $database,
    ], $database === 'ok' ? 200 : 503);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('patients', PatientController::class)->except(['destroy']);
    Route::apiResource('professionals', ProfessionalController::class)->except(['destroy']);

    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'show']);
    Route::post('appointments/{appointment}/start', [AppointmentController::class, 'start']);
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
    Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);

    // Throttle mais brando (CLAUDE.md, seção 7): criar/confirmar consulta são
    // escritas com efeito colateral direto na agenda do profissional.
    Route::middleware('throttle:writes')->group(function () {
        Route::post('appointments', [AppointmentController::class, 'store']);
        Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    });
});
