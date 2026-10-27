<?php

use App\Actions\Vales\ConfirmarViajesPorVale;
use App\Actions\Viajes\RegistrarViajes;
use App\Enums\RolUsuario;
use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use App\Models\Botadero;
use App\Models\Cantera;
use App\Models\CanteraMaterial;
use App\Models\Consorcio;
use App\Models\Material;
use App\Models\Obra;
use App\Models\Tarifa;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Volqueta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * El escenario mínimo con los datos reales del negocio: una obra, dos rutas
 * (una con tarifa y otra sin), una volqueta de tercero y una propia.
 *
 * @return array<string, mixed>
 */
function escenarioBase(): array
{
    $usuario = User::create([
        'name' => 'Digitador',
        'email' => 'dig@gruptous.co',
        'password' => 'secreto123',
        'rol' => RolUsuario::Usuario,
    ]);

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@gruptous.co',
        'password' => 'secreto123',
        'rol' => RolUsuario::Administrador,
    ]);

    $consorcio = Consorcio::create(['razon_social' => 'Consorcio Parque San Jerónimo', 'nit' => '900000000-1']);
    $obra = Obra::create(['consorcio_id' => $consorcio->id, 'nombre' => 'Parque San Jerónimo (Lagunas)']);

    $cantera = Cantera::create(['nombre' => 'Fénix - Orión']);
    $otraCantera = Cantera::create(['nombre' => 'Las Palomas']);
    $botadero = Botadero::create(['nombre' => 'Botadero Km 8']);

    $relleno = Material::create(['nombre' => 'relleno']);

    // Con precio: sin él no se registra el viaje, y "sin tarifa" debe fallar por la tarifa.
    $canteraMaterial = CanteraMaterial::create(['cantera_id' => $cantera->id, 'material_id' => $relleno->id, 'precio_compra_m3' => 10_000]);
    $sinTarifa = CanteraMaterial::create(['cantera_id' => $otraCantera->id, 'material_id' => $relleno->id, 'precio_compra_m3' => 10_000]);

    $propietario = Tercero::create(['nombre' => 'Hugo', 'apellido' => 'Cortés', 'cedula' => '10000001']);
    $empresa = Tercero::create(['nombre' => 'Grup Tous', 'cedula' => '900000000']);
    $conductor = Tercero::create(['nombre' => 'José', 'apellido' => 'Padilla', 'cedula' => '10000002']);

    $volqueta = Volqueta::create([
        'placa' => 'UQE597',
        'propietario_id' => $propietario->id,
        'capacidad_m3' => 14,
    ]);

    $volquetaPropia = Volqueta::create([
        'placa' => 'MTA804',
        'propietario_id' => $empresa->id,
        'conductor_habitual_id' => $conductor->id,
        'capacidad_m3' => 14,
        'es_propia' => true,
    ]);

    // Al consorcio por m³, al dueño por viaje: la diferencia es el margen.
    $tarifaCobro = Tarifa::create([
        'tipo' => TipoTarifa::Cobro,
        'tipo_viaje' => TipoViaje::Suministro,
        'obra_id' => $obra->id,
        'cantera_id' => $cantera->id,
        'valor' => 25_000,
        'unidad' => UnidadTarifa::M3,
        'fecha_inicio' => '2026-08-01',
    ]);

    $tarifaPago = Tarifa::create([
        'tipo' => TipoTarifa::PagoPropietario,
        'tipo_viaje' => TipoViaje::Suministro,
        'obra_id' => $obra->id,
        'cantera_id' => $cantera->id,
        'tercero_id' => $propietario->id,
        'valor' => 200_000,
        'unidad' => UnidadTarifa::Viaje,
        'fecha_inicio' => '2026-08-01',
    ]);

    return compact(
        'usuario', 'admin', 'consorcio', 'obra', 'cantera', 'otraCantera', 'botadero',
        'propietario', 'empresa', 'conductor', 'volqueta', 'volquetaPropia',
        'tarifaCobro', 'tarifaPago',
    ) + [
        'cantera_material' => $canteraMaterial,
        'cantera_material_sin_tarifa' => $sinTarifa,
        'tarifa_cobro' => $tarifaCobro,
        'tarifa_pago' => $tarifaPago,
        'volqueta_propia' => $volquetaPropia,
    ];
}

/**
 * @param  array<string, mixed>  $escenario
 * @return Collection<int, Viaje>
 */
function registrarViajes(array $escenario, int $veces = 1, string $fecha = '2026-08-25'): Collection
{
    return app(RegistrarViajes::class)(
        cabecera: [
            'obra_id' => $escenario['obra']->id,
            'tipo_viaje' => TipoViaje::Suministro->value,
            'fecha_viaje' => $fecha,
            'cantera_material_id' => $escenario['cantera_material']->id,
        ],
        filas: array_fill(0, $veces, ['volqueta_id' => $escenario['volqueta']->id, 'm3' => 14]),
        usuario: $escenario['usuario'],
    );
}

/**
 * @param  array<string, mixed>  $escenario
 * @param  Collection<int, Viaje>  $viajes
 */
function confirmarTodos(array $escenario, Collection $viajes, int $numeroVale = 1531): void
{
    app(ConfirmarViajesPorVale::class)(
        datosVale: [
            'numero' => $numeroVale,
            'obra_id' => $escenario['obra']->id,
            'fecha_vale' => '2026-08-25',
        ],
        viajes: $viajes->map(fn (Viaje $v) => ['viaje_id' => $v->id])->all(),
        usuario: $escenario['usuario'],
    );
}
