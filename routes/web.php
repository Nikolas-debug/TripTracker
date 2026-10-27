<?php

use Illuminate\Support\Facades\Route;

/**
 * Una sola vista: el resto de la navegación la maneja Vue Router del lado del
 * cliente. Cualquier ruta que no sea /api ni un archivo real cae aquí.
 */
Route::view('/{cualquiera?}', 'app')
    ->where('cualquiera', '^(?!api|storage|build).*$')
    ->name('app');
