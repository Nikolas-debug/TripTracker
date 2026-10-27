<?php

use App\Actions\Tarifas\CambiarTarifa;
use App\Actions\Viajes\RegistrarViajes;
use App\Enums\EstadoViaje;
use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use App\Exceptions\TarifaNoEncontrada;
use App\Models\Tarifa;
use App\Models\Viaje;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->escenario = escenarioBase();
});

it('congela los valores de la tarifa en el viaje', function () {
    $viajes = app(RegistrarViajes::class)(
        cabecera: [
            'obra_id' => $this->escenario['obra']->id,
            'tipo_viaje' => TipoViaje::Suministro->value,
            'fecha_viaje' => '2026-08-25',
            'cantera_material_id' => $this->escenario['cantera_material']->id,
        ],
        filas: [['volqueta_id' => $this->escenario['volqueta']->id, 'm3' => 14]],
        usuario: $this->escenario['usuario'],
    );

    $viaje = $viajes->first();

    expect($viaje->valor_cobro_unitario)->toBe(25_000)
        ->and($viaje->unidad_cobro)->toBe(UnidadTarifa::M3)
        ->and($viaje->valor_pago_unitario)->toBe(200_000)
        ->and($viaje->unidad_pago)->toBe(UnidadTarifa::Viaje)
        ->and($viaje->estado)->toBe(EstadoViaje::PorConfirmar)
        ->and($viaje->vale_id)->toBeNull();

    // 25.000 x 14 m³ de flete más 10.000 x 14 m³ de material al consorcio,
    // 200.000 planos al dueño. El material entra y sale igual: no da margen.
    expect($viaje->valor_material_m3)->toBe(10_000)
        ->and($viaje->totalFlete())->toBe(350_000)
        ->and($viaje->totalMaterial())->toBe(140_000)
        ->and($viaje->totalCobro())->toBe(490_000)
        ->and($viaje->totalPagoPropietario())->toBe(200_000)
        ->and($viaje->margen())->toBe(150_000);
});

it('no reescribe viajes ya registrados cuando sube la tarifa', function () {
    $viaje = app(RegistrarViajes::class)(
        cabecera: [
            'obra_id' => $this->escenario['obra']->id,
            'tipo_viaje' => TipoViaje::Suministro->value,
            'fecha_viaje' => '2026-08-25',
            'cantera_material_id' => $this->escenario['cantera_material']->id,
        ],
        filas: [['volqueta_id' => $this->escenario['volqueta']->id, 'm3' => 14]],
        usuario: $this->escenario['usuario'],
    )->first();

    // Sube el combustible y se pacta un precio nuevo.
    app(CambiarTarifa::class)(
        $this->escenario['tarifa_cobro'],
        nuevoValor: 30_000,
        desde: '2026-09-01',
        motivo: 'alza del combustible',
    );

    // El viaje de agosto sigue valiendo lo de agosto.
    expect($viaje->fresh()->valor_cobro_unitario)->toBe(25_000)
        ->and($viaje->fresh()->totalFlete())->toBe(350_000);

    // Y la tarifa vieja quedó cerrada, no borrada.
    expect(Tarifa::count())->toBe(3)
        ->and($this->escenario['tarifa_cobro']->fresh()->fecha_fin->toDateString())->toBe('2026-08-31');
});

it('falla el lote entero si una ruta no tiene tarifa pactada', function () {
    $sinTarifa = $this->escenario['cantera_material_sin_tarifa'];

    expect(fn () => app(RegistrarViajes::class)(
        cabecera: [
            'obra_id' => $this->escenario['obra']->id,
            'tipo_viaje' => TipoViaje::Suministro->value,
            'fecha_viaje' => '2026-08-25',
            'cantera_material_id' => $sinTarifa->id,
        ],
        filas: [
            ['volqueta_id' => $this->escenario['volqueta']->id, 'm3' => 14],
            ['volqueta_id' => $this->escenario['volqueta']->id, 'm3' => 14],
        ],
        usuario: $this->escenario['usuario'],
    ))->toThrow(TarifaNoEncontrada::class);

    // Ni uno solo debe haber quedado guardado: mejor nada que la mitad.
    assertDatabaseCount('viajes', 0);
});

it('paga por porcentaje cuando la volqueta es de la empresa', function () {
    Tarifa::create([
        'tipo' => TipoTarifa::PagoConductor,
        'tipo_viaje' => TipoViaje::Suministro,
        'obra_id' => $this->escenario['obra']->id,
        'cantera_id' => $this->escenario['cantera']->id,
        'tercero_id' => $this->escenario['conductor']->id,
        'valor_base' => 200_000,
        'porcentaje' => 15,
        'unidad' => UnidadTarifa::Porcentaje,
        'fecha_inicio' => '2026-08-01',
    ]);

    $viaje = app(RegistrarViajes::class)(
        cabecera: [
            'obra_id' => $this->escenario['obra']->id,
            'tipo_viaje' => TipoViaje::Suministro->value,
            'fecha_viaje' => '2026-08-25',
            'cantera_material_id' => $this->escenario['cantera_material']->id,
        ],
        filas: [[
            'volqueta_id' => $this->escenario['volqueta_propia']->id,
            'm3' => 14,
            'conductor_id' => $this->escenario['conductor']->id,
        ]],
        usuario: $this->escenario['usuario'],
    )->first();

    // Se guardan los tres componentes, no solo el resultado.
    expect($viaje->valor_base_conductor)->toBe(200_000)
        ->and((float) $viaje->porcentaje_conductor)->toBe(15.0)
        ->and($viaje->valor_pago_conductor)->toBe(30_000)
        ->and($viaje->valor_pago_unitario)->toBeNull();
});

it('rechaza en la base un suministro con botadero', function () {
    expect(fn () => Viaje::create([
        'volqueta_id' => $this->escenario['volqueta']->id,
        'tipo_viaje' => TipoViaje::Suministro,
        'obra_id' => $this->escenario['obra']->id,
        'cantera_material_id' => $this->escenario['cantera_material']->id,
        'botadero_id' => $this->escenario['botadero']->id,
        'fecha_viaje' => '2026-08-25',
        'm3_registrado' => 14,
        'valor_cobro_unitario' => 25_000,
        'unidad_cobro' => UnidadTarifa::M3,
        'registrado_por' => $this->escenario['usuario']->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
