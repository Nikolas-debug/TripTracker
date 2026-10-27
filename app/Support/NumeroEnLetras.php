<?php

namespace App\Support;

/**
 * Pesos en letras para la cuenta de cobro: "CINCUENTA Y TRES MILLONES
 * DOSCIENTOS MIL PESOS". A mano porque el servidor no trae la extensión intl.
 */
final class NumeroEnLetras
{
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = [3 => 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CENTENAS = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos',
        'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos',
    ];

    public static function pesos(int $valor): string
    {
        // Millones exactos llevan "de": "CINCO MILLONES DE PESOS".
        $de = $valor >= 1_000_000 && $valor % 1_000_000 === 0 ? ' de' : '';

        return mb_strtoupper(self::convertir($valor).$de.' pesos');
    }

    public static function convertir(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $texto = $millones === 1 ? 'un millón' : self::apocopar(self::convertir($millones)).' millones';

            return self::unir($texto, $n % 1_000_000);
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $texto = $miles === 1 ? 'mil' : self::apocopar(self::convertir($miles)).' mil';

            return self::unir($texto, $n % 1000);
        }

        if ($n === 100) {
            return 'cien';
        }

        $resto = $n % 100;
        $decenas = $resto < 30
            ? self::UNIDADES[$resto]
            : self::DECENAS[intdiv($resto, 10)].($resto % 10 ? ' y '.self::UNIDADES[$resto % 10] : '');

        return trim(self::CENTENAS[intdiv($n, 100)].' '.$decenas);
    }

    private static function unir(string $texto, int $resto): string
    {
        return $resto ? $texto.' '.self::convertir($resto) : $texto;
    }

    /** Delante de "mil" y "millones": veintiuno -> veintiún, uno -> un. */
    private static function apocopar(string $texto): string
    {
        return preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $texto);
    }
}
