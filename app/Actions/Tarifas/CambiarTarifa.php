<?php

namespace App\Actions\Tarifas;

use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sube (o baja) un precio sin reescribir la historia.
 *
 * Un cambio de tarifa NUNCA es un UPDATE al valor: se cierra la vigente
 * poniéndole fecha_fin y se crea una nueva. Así queda registrado desde cuándo
 * y por qué cambió — el alza del combustible, un imprevisto — y los viajes ya
 * registrados conservan el valor que tenían.
 */
class CambiarTarifa
{
    public function __invoke(Tarifa $vigente, int $nuevoValor, string $desde, ?string $motivo = null, ?User $usuario = null): Tarifa
    {
        $desde = Carbon::parse($desde);

        return DB::transaction(function () use ($vigente, $nuevoValor, $desde, $motivo, $usuario) {
            // La anterior queda cerrada el día antes de que empiece la nueva.
            $vigente->update(['fecha_fin' => $desde->copy()->subDay()]);

            $atributos = $vigente->only([
                'tipo', 'tipo_viaje', 'obra_id', 'cantera_id', 'botadero_id',
                'material_id', 'tercero_id', 'unidad', 'valor_base', 'porcentaje',
            ]);

            return Tarifa::create($atributos + [
                'valor' => $nuevoValor,
                'fecha_inicio' => $desde,
                'fecha_fin' => null,
                'motivo_cambio' => $motivo,
                'creado_por' => $usuario?->id,
            ]);
        });
    }
}
