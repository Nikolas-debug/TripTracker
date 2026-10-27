<?php

use App\Http\Controllers\Api\AltaConsorcioController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotaderoController;
use App\Http\Controllers\Api\CanteraController;
use App\Http\Controllers\Api\CanteraMaterialController;
use App\Http\Controllers\Api\ConsorcioController;
use App\Http\Controllers\Api\LiquidacionController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ObraController;
use App\Http\Controllers\Api\PanelController;
use App\Http\Controllers\Api\TarifaController;
use App\Http\Controllers\Api\TerceroController;
use App\Http\Controllers\Api\ValeController;
use App\Http\Controllers\Api\ViajeController;
use App\Http\Controllers\Api\VolquetaController;
use Illuminate\Support\Facades\Route;

// Abierta a internet: seis intentos por minuto frenan a quien adivine claves.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // --- Catálogos ---------------------------------------------------
    Route::apiResource('terceros', TerceroController::class);
    Route::apiResource('volquetas', VolquetaController::class);
    Route::apiResource('consorcios', ConsorcioController::class);
    Route::apiResource('obras', ObraController::class);
    Route::apiResource('canteras', CanteraController::class);
    Route::apiResource('botaderos', BotaderoController::class);
    Route::apiResource('materiales', MaterialController::class);
    Route::apiResource('cantera-material', CanteraMaterialController::class);

    // --- Tarifas -----------------------------------------------------
    // OJO: 'resolver' va ANTES del apiResource. Si no, Laravel la toma como
    // tarifas/{tarifa} con el id "resolver" y nunca llega aquí.
    Route::get('tarifas/resolver', [TarifaController::class, 'resolver']);
    Route::post('tarifas/{tarifa}/cambiar', [TarifaController::class, 'cambiar'])
        ->can('cambiar', 'tarifa');
    Route::apiResource('tarifas', TarifaController::class)->only(['index', 'store', 'show']);

    // --- Viajes ------------------------------------------------------
    Route::post('viajes/{viaje}/anular', [ViajeController::class, 'anular']);
    Route::apiResource('viajes', ViajeController::class)->only(['index', 'store', 'show', 'update']);

    // --- Vales -------------------------------------------------------
    // Los viajes que esperan vale, para armar la pantalla de confirmación.
    Route::get('vales/pendientes', [ValeController::class, 'pendientes']);
    Route::apiResource('vales', ValeController::class)->only(['index', 'store', 'show']);

    // --- Liquidaciones y anticipos -----------------------------------
    Route::post('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrar']);
    Route::post('liquidaciones/{liquidacion}/anticipos', [LiquidacionController::class, 'agregarAnticipo']);
    Route::delete('anticipos/{anticipo}', [LiquidacionController::class, 'eliminarAnticipo']);
    Route::apiResource('liquidaciones', LiquidacionController::class)->only(['index', 'store', 'show']);

    // --- Solo administrador -------------------------------------------
    Route::middleware('admin')->group(function () {
        // El tablero completo en una sola llamada: seis consultas del mismo
        // periodo, para que los números de la pantalla no vengan de
        // instantes distintos.
        Route::get('panel', PanelController::class);

        // Alta guiada de un contrato nuevo: consorcio + obras + tarifas.
        Route::post('consorcios/alta-completa', AltaConsorcioController::class);
    });
});
