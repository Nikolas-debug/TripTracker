<?php

it('el usuario crea tarifas de pago pero no la de cobro al consorcio', function () {
    $e = escenarioBase();

    $ruta = [
        'tipo_viaje' => 'suministro',
        'obra_id' => $e['obra']->id,
        'cantera_id' => $e['otraCantera']->id,
        'fecha_inicio' => '2026-08-01',
    ];

    $this->actingAs($e['usuario'])
        ->postJson('/api/tarifas', $ruta + ['tipo' => 'cobro', 'unidad' => 'm3', 'valor' => 25_000])
        ->assertForbidden();

    $this->actingAs($e['usuario'])
        ->postJson('/api/tarifas', $ruta + ['tipo' => 'pago_propietario', 'unidad' => 'viaje', 'valor' => 200_000])
        ->assertCreated();

    $this->actingAs($e['admin'])
        ->postJson('/api/tarifas', $ruta + ['tipo' => 'cobro', 'unidad' => 'm3', 'valor' => 25_000])
        ->assertCreated();
});
