<?php

namespace App\Policies;

use App\Models\Liquidacion;
use App\Models\User;

class LiquidacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * Cerrar una cuenta congela plata y bloquea viajes: solo el administrador.
     */
    public function cerrar(User $usuario, Liquidacion $liquidacion): bool
    {
        return $usuario->esAdministrador() && $liquidacion->estaAbierta();
    }
}
