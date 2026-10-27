<?php

namespace App\Actions\Vales;

use App\Enums\EstadoViaje;
use App\Exceptions\ViajeBloqueado;
use App\Models\User;
use App\Models\Vale;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra el vale y confirma de una vez todos los viajes que ampara.
 *
 * Es una sola transacción a propósito: si se cayera a la mitad quedarían
 * viajes confirmados sin vale, que es exactamente lo que el modelo intenta
 * evitar — confirmado es lo que se paga.
 *
 * Los m³ del vale se guardan en m3_confirmado SIN tocar m3_registrado: la
 * diferencia entre los dos es la señal de seguimiento del administrador.
 */
class ConfirmarViajesPorVale
{
    /**
     * @param  array{numero:int, obra_id:int, fecha_vale:string, fecha_recepcion?:string|null, firmado_por?:string|null, observacion?:string|null}  $datosVale
     * @param  array<int, array{viaje_id:int, m3_confirmado?:int|null}>  $viajes
     */
    public function __invoke(array $datosVale, array $viajes, User $usuario): Vale
    {
        return DB::transaction(function () use ($datosVale, $viajes, $usuario) {
            $vale = Vale::create([
                'numero' => $datosVale['numero'],
                'obra_id' => $datosVale['obra_id'],
                'fecha_vale' => $datosVale['fecha_vale'],
                'fecha_recepcion' => $datosVale['fecha_recepcion'] ?? now()->toDateString(),
                'firmado_por' => $datosVale['firmado_por'] ?? null,
                'recibido_por' => $usuario->id,
                'observacion' => $datosVale['observacion'] ?? null,
            ]);

            foreach ($viajes as $fila) {
                $viaje = Viaje::lockForUpdate()->findOrFail($fila['viaje_id']);

                $this->verificar($viaje, $vale);

                $viaje->update([
                    'estado' => EstadoViaje::Confirmado,
                    'vale_id' => $vale->id,
                    'm3_confirmado' => $fila['m3_confirmado'] ?? $viaje->m3_registrado,
                    'fecha_confirmacion' => now(),
                    'confirmado_por' => $usuario->id,
                ]);
            }

            return $vale->load('viajes');
        });
    }

    private function verificar(Viaje $viaje, Vale $vale): void
    {
        if ($viaje->estaLiquidado()) {
            throw new ViajeBloqueado("El viaje {$viaje->id} ya está liquidado.");
        }

        if ($viaje->estado === EstadoViaje::Anulado) {
            throw ValidationException::withMessages([
                'viajes' => "El viaje {$viaje->id} está anulado y no se puede confirmar.",
            ]);
        }

        // Un vale es de una obra: no puede amparar viajes de otra.
        if ($viaje->obra_id !== $vale->obra_id) {
            throw ValidationException::withMessages([
                'viajes' => "El viaje {$viaje->id} no es de la obra del vale.",
            ]);
        }
    }
}
