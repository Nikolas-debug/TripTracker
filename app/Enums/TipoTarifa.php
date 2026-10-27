<?php

namespace App\Enums;

enum TipoTarifa: string
{
    /** Lo que se le cobra al consorcio. */
    case Cobro = 'cobro';

    /** Lo que se le paga al dueño de la volqueta. */
    case PagoPropietario = 'pago_propietario';

    /** Porcentaje al conductor de una volqueta propia. */
    case PagoConductor = 'pago_conductor';
}
