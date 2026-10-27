<?php

namespace App\Enums;

enum EstadoLiquidacion: string
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';
    case Pagada = 'pagada';
}
