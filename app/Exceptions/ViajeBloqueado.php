<?php

namespace App\Exceptions;

use RuntimeException;

/** Se intentó tocar un viaje que ya entró en una liquidación. */
class ViajeBloqueado extends RuntimeException
{
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
