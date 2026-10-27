<?php

namespace App\Enums;

enum TipoViaje: string
{
    /** Cantera -> obra. */
    case Suministro = 'suministro';

    /** Obra -> botadero. */
    case Retiro = 'retiro';
}
