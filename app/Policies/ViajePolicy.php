<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viaje;

class ViajePolicy
{
    /** Digitar viajes es el trabajo del usuario. */
    public function create(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function update(User $usuario, Viaje $viaje): bool
    {
        return $usuario->activo && ! $viaje->estaLiquidado();
    }

    /**
     * Anular es admitir que un viaje acordado no ocurrió: lo decide el
     * administrador, no quien digita.
     */
    public function anular(User $usuario, Viaje $viaje): bool
    {
        return $usuario->esAdministrador() && ! $viaje->estaLiquidado();
    }
}
