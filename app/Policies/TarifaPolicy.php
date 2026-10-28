<?php

namespace App\Policies;

use App\Enums\TipoTarifa;
use App\Enums\UnidadTarifa;
use App\Models\Tarifa;
use App\Models\User;

class TarifaPolicy
{
    /** Lo que se le cobra al consorcio lo pacta solo el administrador. */
    public function create(User $usuario, TipoTarifa $tipo): bool
    {
        return $tipo !== TipoTarifa::Cobro || $usuario->esAdministrador();
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
