<?php

namespace App\Policies;

use App\Enums\UnidadTarifa;
use App\Models\Tarifa;
use App\Models\User;

class TarifaPolicy
{
    public function create(User $usuario): bool
    {
        return $usuario->esAdministrador();
    }

    /**
     * Cambiar un precio pactado es decisión del administrador. Las de
     * porcentaje no: CambiarTarifa solo reemplaza el valor, y en ellas el
     * pago sale de la base y el porcentaje.
     */
    public function cambiar(User $usuario, Tarifa $tarifa): bool
    {
        return $usuario->esAdministrador()
            && $tarifa->estaVigente()
            && $tarifa->unidad !== UnidadTarifa::Porcentaje;
    }
}
