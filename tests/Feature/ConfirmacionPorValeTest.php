<?php

use App\Actions\Vales\ConfirmarViajesPorVale;
use App\Enums\EstadoViaje;
use App\Models\Obra;
use App\Models\Vale;
use App\Models\Viaje;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->escenario = escenarioBase();
    $this->viajes = registrarViajes($this->escenario, veces: 3);
});

it('confirma varios viajes con un solo vale', function () {
    $vale = app(ConfirmarViajesPorVale::class)(
        datosVale: [
            'numero' => 1531,
            'obra_id' => $this->escenario['obra']->id,
            'fecha_vale' => '2026-08-25',
            'firmado_por' => 'Ing. Paternina',
        ],
        viajes: $this->viajes->map(fn (Viaje $v) => ['viaje_id' => $v->id])->all(),
        usuario: $this->escenario['usuario'],
    );

    expect($vale->viajes)->toHaveCount(3);

    $this->viajes->each(function (Viaje $viaje) use ($vale) {
        $viaje->refresh();
        expect($viaje->estado)->toBe(EstadoViaje::Confirmado)
            ->and($viaje->vale_id)->toBe($vale->id)
            ->and($viaje->fecha_confirmacion)->not->toBeNull();
    });
});

it('guarda los m³ del vale sin borrar los que se habían registrado', function () {
    // El conductor dijo 14, el vale dice 12.
    app(ConfirmarViajesPorVale::class)(
        datosVale: ['numero' => 1533, 'obra_id' => $this->escenario['obra']->id, 'fecha_vale' => '2026-08-25'],
        viajes: [['viaje_id' => $this->viajes->first()->id, 'm3_confirmado' => 12]],
        usuario: $this->escenario['usuario'],
    );

    $viaje = $this->viajes->first()->fresh();

    expect($viaje->m3_registrado)->toBe(14)
        ->and($viaje->m3_confirmado)->toBe(12)
        // El cobro se recalcula con los m³ reales: (25.000 + 10.000 de material) x 12.
        ->and($viaje->totalCobro())->toBe(420_000)
        // Pero el pago al dueño es por viaje: no cambia.
        ->and($viaje->totalPagoPropietario())->toBe(200_000);
});

it('no deja el vale a medias si un viaje no se puede confirmar', function () {
    $otraObra = Obra::create([
        'consorcio_id' => $this->escenario['obra']->consorcio_id,
        'nombre' => 'Otra obra',
    ]);

    $ajeno = $this->viajes->last();
    $ajeno->forceFill(['obra_id' => $otraObra->id])->saveQuietly();

    expect(fn () => app(ConfirmarViajesPorVale::class)(
        datosVale: ['numero' => 1540, 'obra_id' => $this->escenario['obra']->id, 'fecha_vale' => '2026-08-25'],
        viajes: $this->viajes->map(fn (Viaje $v) => ['viaje_id' => $v->id])->all(),
        usuario: $this->escenario['usuario'],
    ))->toThrow(Illuminate\Validation\ValidationException::class);

    // La transacción se revierte entera: ni vale creado, ni viajes confirmados.
    assertDatabaseCount('vales', 0);
    expect(Viaje::confirmados()->count())->toBe(0);
});

it('permite el mismo número de vale en obras distintas', function () {
    $otraObra = Obra::create([
        'consorcio_id' => $this->escenario['obra']->consorcio_id,
        'nombre' => 'Las Palomas KM 30',
    ]);

    Vale::create(['numero' => 1531, 'obra_id' => $this->escenario['obra']->id, 'fecha_vale' => '2026-08-25']);
    Vale::create(['numero' => 1531, 'obra_id' => $otraObra->id, 'fecha_vale' => '2026-08-25']);

    expect(Vale::where('numero', 1531)->count())->toBe(2);
});

it('rechaza el mismo número de vale repetido en la misma obra', function () {
    Vale::create(['numero' => 1531, 'obra_id' => $this->escenario['obra']->id, 'fecha_vale' => '2026-08-25']);

    expect(fn () => Vale::create([
        'numero' => 1531,
        'obra_id' => $this->escenario['obra']->id,
        'fecha_vale' => '2026-08-26',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('rechaza en la base un viaje confirmado sin vale', function () {
    $viaje = $this->viajes->first();

    expect(fn () => $viaje->forceFill([
        'estado' => EstadoViaje::Confirmado,
        'm3_confirmado' => 14,
        'fecha_confirmacion' => now(),
        // sin vale_id a propósito
    ])->save())->toThrow(Illuminate\Database\QueryException::class);
});
