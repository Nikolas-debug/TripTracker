<?php

namespace App\Actions\Liquidaciones;

use App\Enums\EstadoLiquidacion;
use App\Exceptions\LiquidacionCerrada;
use App\Models\Liquidacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cierra la cuenta: se congelan los totales y los viajes quedan bloqueados
 * para edición (lo impide el propio modelo Viaje).
 */
class CerrarLiquidacion
{
    public function __invoke(Liquidacion $liquidacion, User $usuario): Liquidacion
    {
        if (! $liquidacion->estaAbierta()) {
            throw new LiquidacionCerrada("La cuenta #{$liquidacion->numero_cuenta} ya está cerrada.");
        }

        return DB::transaction(function () use ($liquidacion, $usuario) {
            $liquidacion->load('anticipos')->cargarViajes()->recalcularTotales();

            $liquidacion->fill([
                'estado' => EstadoLiquidacion::Cerrada,
                'cerrada_por' => $usuario->id,
                'cerrada_en' => now(),
            ])->save();

            return $liquidacion;
        });
    }
}
