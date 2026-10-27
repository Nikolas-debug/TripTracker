<?php

namespace App\Enums;

enum EstadoViaje: string
{
    case PorConfirmar = 'por_confirmar';
    case Confirmado = 'confirmado';
    case Anulado = 'anulado';
}
