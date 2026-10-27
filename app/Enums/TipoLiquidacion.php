<?php

namespace App\Enums;

enum TipoLiquidacion: string
{
    /** Cuenta de cobro a un consorcio. */
    case Cobro = 'cobro';

    /** Cuenta de pago a un dueño o conductor. */
    case Pago = 'pago';

    /** Lo pagado a una cantera por el material. Se paga al cargar: saldo cero. */
    case Material = 'material';
}
