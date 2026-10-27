<?php

namespace App\Http\Controllers\Api;

use App\Actions\Consorcios\RegistrarConsorcioCompleto;
use App\Enums\TipoTarifa;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarConsorcioRequest;
use Illuminate\Http\JsonResponse;

class AltaConsorcioController extends Controller
{
    public function __invoke(RegistrarConsorcioRequest $request, RegistrarConsorcioCompleto $registrar): JsonResponse
    {
        $consorcio = $registrar(
            $request->safe()->except('obras'),
            $request->input('obras'),
            $request->user(),
        );

        // Una obra sin tarifa de cobro bloquea el registro de viajes: se avisa
        // ahora y no cuando el digitador choque con el error.
        $sinTarifa = $consorcio->obras
            ->filter(fn ($obra) => $obra->tarifas->where('tipo', TipoTarifa::Cobro)->isEmpty())
            ->pluck('nombre')
            ->all();

        return response()->json([
            'message' => "Se creó el consorcio con {$consorcio->obras->count()} obras.",
            'consorcio' => [
                'id' => $consorcio->id,
                'razon_social' => $consorcio->razon_social,
                'obras' => $consorcio->obras->map(fn ($o) => [
                    'id' => $o->id,
                    'nombre' => $o->nombre,
                    'tarifas' => $o->tarifas->count(),
                ]),
            ],
            'obras_sin_tarifa_de_cobro' => $sinTarifa,
        ], 201);
    }
}
