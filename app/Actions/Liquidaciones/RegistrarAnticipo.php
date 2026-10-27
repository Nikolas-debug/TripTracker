<?php

namespace App\Actions\Liquidaciones;

use App\Exceptions\LiquidacionCerrada;
use App\Models\Anticipo;
use App\Models\Liquidacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Un abono contra la cuenta. Baja el saldo, como el pie del Excel:
 * "ANTICIPO 13 DE AGOSTO: 8.000.000 -> SALDO PENDIENTE: 15.400.000".
 */
class RegistrarAnticipo
{
    /** @param array{fecha:string, valor:int, observacion?:string|null} $datos */
    public function __invoke(Liquidacion $liquidacion, array $datos, User $usuario): Anticipo
    {
        if (! $liquidacion->estaAbierta()) {
            throw new LiquidacionCerrada('No se pueden agregar anticipos a una cuenta cerrada.');
        }

        // Ya se pagó en la cantera al cargar: no hay saldo que abonar.
        if ($liquidacion->esMaterial()) {
            throw ValidationException::withMessages([
                'valor' => 'La cuenta de material ya está pagada en la cantera: no lleva anticipos.',
            ]);
        }

        return DB::transaction(function () use ($liquidacion, $datos, $usuario) {
            $anticipo = $liquidacion->anticipos()->create([
                'fecha' => $datos['fecha'],
                'valor' => $datos['valor'],
                'observacion' => $datos['observacion'] ?? null,
                'registrado_por' => $usuario->id,
            ]);

            $liquidacion->load('anticipos')->cargarViajes()->recalcularTotales()->save();

            return $anticipo;
        });
    }
}
