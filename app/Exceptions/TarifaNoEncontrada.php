<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No hay tarifa vigente para la ruta. Es un error, no un cero: un viaje
 * guardado en cero se paga en cero y nadie lo nota hasta la liquidación.
 */
class TarifaNoEncontrada extends RuntimeException
{
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'tarifa_no_encontrada',
        ], 422);
    }
}
