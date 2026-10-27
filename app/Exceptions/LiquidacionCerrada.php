<?php

namespace App\Exceptions;

use RuntimeException;

class LiquidacionCerrada extends RuntimeException
{
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
