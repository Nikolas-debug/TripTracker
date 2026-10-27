<?php

namespace App\Actions\Viajes;

use App\Enums\EstadoViaje;
use App\Exceptions\ViajeBloqueado;
use App\Models\Viaje;

/**
 * Un viaje que no se hizo, o cuyo vale nunca apareció, se anula.
 * Nunca se borra: el histórico tiene que poder explicarse después.
 */
class AnularViaje
{
    public function __invoke(Viaje $viaje, ?string $motivo = null): Viaje
    {
        if ($viaje->estaLiquidado()) {
            throw new ViajeBloqueado("El viaje {$viaje->id} está en una liquidación y no se puede anular.");
        }

        $viaje->update([
            'estado' => EstadoViaje::Anulado,
            'observaciones' => trim(($viaje->observaciones ?? '')."\nAnulado: ".($motivo ?? 'sin motivo')),
        ]);

        return $viaje;
    }
}
