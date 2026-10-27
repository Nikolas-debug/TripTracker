<?php

use App\Support\NumeroEnLetras;

it('escribe los pesos como en la cuenta de cobro', function (int $valor, string $esperado) {
    expect(NumeroEnLetras::pesos($valor))->toBe($esperado);
})->with([
    [53_200_000, 'CINCUENTA Y TRES MILLONES DOSCIENTOS MIL PESOS'],
    [5_000_000, 'CINCO MILLONES DE PESOS'],
    [1_000_000, 'UN MILLÓN DE PESOS'],
    [21_350_000, 'VEINTIÚN MILLONES TRESCIENTOS CINCUENTA MIL PESOS'],
    [101_000, 'CIENTO UN MIL PESOS'],
    [1_100, 'MIL CIEN PESOS'],
    [31_516, 'TREINTA Y UN MIL QUINIENTOS DIECISÉIS PESOS'],
    [1_000_000_000, 'MIL MILLONES DE PESOS'],
    [0, 'CERO PESOS'],
]);
