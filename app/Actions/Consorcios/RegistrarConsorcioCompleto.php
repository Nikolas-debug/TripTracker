<?php

namespace App\Actions\Consorcios;

use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use App\Models\Consorcio;
use App\Models\Obra;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta un contrato nuevo completo: el consorcio, sus obras y las
 * tarifas de las rutas de esas obras.
 *
 * Va junto y en una transacción por una razón concreta: una obra sin tarifas
 * pactadas no sirve para nada — el registro de viajes falla con "no hay tarifa
 * vigente para esta ruta" y el digitador queda bloqueado sin saber por qué.
 * Crear las tres cosas en pasos sueltos es justamente cómo se llega a esa
 * situación.
 */
class RegistrarConsorcioCompleto
{
    /**
     * @param  array<string, mixed>  $datosConsorcio
     * @param  array<int, array{nombre:string, direccion?:string|null, municipio?:string|null, tarifas?:array}>  $obras
     */
    public function __invoke(array $datosConsorcio, array $obras, User $usuario): Consorcio
    {
        return DB::transaction(function () use ($datosConsorcio, $obras, $usuario) {
            $consorcio = Consorcio::create($datosConsorcio);

            foreach ($obras as $datosObra) {
                $obra = $consorcio->obras()->create([
                    'nombre' => $datosObra['nombre'],
                    'direccion' => $datosObra['direccion'] ?? null,
                    'municipio' => $datosObra['municipio'] ?? $consorcio->municipio,
                ]);

                foreach ($datosObra['tarifas'] ?? [] as $datosTarifa) {
                    $this->crearTarifa($obra, $datosTarifa, $usuario);
                }
            }

            return $consorcio->load('obras.tarifas');
        });
    }

    /** @param array<string, mixed> $datos */
    private function crearTarifa(Obra $obra, array $datos, User $usuario): Tarifa
    {
        $tipo = TipoTarifa::from($datos['tipo']);
        $tipoViaje = TipoViaje::from($datos['tipo_viaje']);

        return Tarifa::create([
            'tipo' => $tipo,
            'tipo_viaje' => $tipoViaje,
            'obra_id' => $obra->id,
            'cantera_id' => $tipoViaje === TipoViaje::Suministro ? $datos['cantera_id'] : null,
            'botadero_id' => $tipoViaje === TipoViaje::Retiro ? $datos['botadero_id'] : null,
            // El material solo se llena para una excepción de precio puntual;
            // lo normal es una tarifa por ruta, sin distinguir material.
            'material_id' => $datos['material_id'] ?? null,
            'tercero_id' => $tipo === TipoTarifa::Cobro ? null : $datos['tercero_id'],
            'valor' => $tipo === TipoTarifa::PagoConductor ? null : $datos['valor'],
            'valor_base' => $tipo === TipoTarifa::PagoConductor ? $datos['valor_base'] : null,
            'porcentaje' => $tipo === TipoTarifa::PagoConductor ? $datos['porcentaje'] : null,
            'unidad' => UnidadTarifa::from($datos['unidad']),
            'fecha_inicio' => $datos['fecha_inicio'],
            'motivo_cambio' => 'Tarifa inicial del contrato',
            'creado_por' => $usuario->id,
        ]);
    }
}
