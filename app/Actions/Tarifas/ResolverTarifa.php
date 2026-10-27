<?php

namespace App\Actions\Tarifas;

use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Exceptions\TarifaNoEncontrada;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Busca la tarifa vigente para una RUTA.
 *
 * El precio lo define la distancia del recorrido, no el material: por eso la
 * clave es (obra + cantera) en un suministro y (obra + botadero) en un retiro.
 * El material solo entra para excepciones puntuales.
 *
 * Resolución por especificidad — gana la primera que exista:
 *
 *   suministro: 1) obra + cantera + material   (excepción)
 *               2) obra + cantera              (el caso normal)
 *   retiro:     1) obra + botadero
 *
 * En las tarifas de pago la búsqueda incluye al dueño o al conductor.
 */
class ResolverTarifa
{
    /**
     * @throws TarifaNoEncontrada cuando no hay ninguna vigente para la ruta.
     */
    public function __invoke(
        TipoTarifa $tipo,
        TipoViaje $tipoViaje,
        int $obraId,
        ?int $canteraId = null,
        ?int $botaderoId = null,
        ?int $materialId = null,
        ?int $terceroId = null,
        Carbon|string|null $fecha = null,
    ): Tarifa {
        $tarifa = $this->buscar($tipo, $tipoViaje, $obraId, $canteraId, $botaderoId, $materialId, $terceroId, $fecha);

        if ($tarifa === null) {
            throw new TarifaNoEncontrada($this->explicar($tipo, $tipoViaje, $obraId, $canteraId, $botaderoId, $terceroId));
        }

        return $tarifa;
    }

    /** Igual que __invoke pero devuelve null en vez de lanzar. */
    public function buscar(
        TipoTarifa $tipo,
        TipoViaje $tipoViaje,
        int $obraId,
        ?int $canteraId = null,
        ?int $botaderoId = null,
        ?int $materialId = null,
        ?int $terceroId = null,
        Carbon|string|null $fecha = null,
    ): ?Tarifa {
        $fecha = $fecha ? Carbon::parse($fecha) : Carbon::today();

        foreach ($this->candidatas($tipoViaje, $canteraId, $botaderoId, $materialId) as $criterio) {
            $tarifa = Tarifa::query()
                ->where('tipo', $tipo)
                ->where('tipo_viaje', $tipoViaje)
                ->where('obra_id', $obraId)
                ->when(
                    $tipo === TipoTarifa::Cobro,
                    fn (Builder $q) => $q->whereNull('tercero_id'),
                    fn (Builder $q) => $q->where('tercero_id', $terceroId),
                )
                ->where(fn (Builder $q) => $this->aplicarCriterio($q, $criterio))
                ->vigenteEn($fecha)
                ->orderByDesc('fecha_inicio')
                ->first();

            if ($tarifa !== null) {
                return $tarifa;
            }
        }

        return null;
    }

    /**
     * Los criterios a probar, del más específico al más general.
     *
     * @return array<int, array<string, int|null>>
     */
    private function candidatas(TipoViaje $tipoViaje, ?int $canteraId, ?int $botaderoId, ?int $materialId): array
    {
        if ($tipoViaje === TipoViaje::Retiro) {
            return [
                ['botadero_id' => $botaderoId, 'cantera_id' => null, 'material_id' => null],
            ];
        }

        $criterios = [];

        // 1. Excepción por material en esa ruta.
        if ($materialId !== null) {
            $criterios[] = ['cantera_id' => $canteraId, 'material_id' => $materialId, 'botadero_id' => null];
        }

        // 2. La ruta, sin distinguir material: el caso normal.
        $criterios[] = ['cantera_id' => $canteraId, 'material_id' => null, 'botadero_id' => null];

        return $criterios;
    }

    /** @param array<string, int|null> $criterio */
    private function aplicarCriterio(Builder $query, array $criterio): Builder
    {
        foreach ($criterio as $columna => $valor) {
            $valor === null
                ? $query->whereNull($columna)
                : $query->where($columna, $valor);
        }

        return $query;
    }

    private function explicar(
        TipoTarifa $tipo,
        TipoViaje $tipoViaje,
        int $obraId,
        ?int $canteraId,
        ?int $botaderoId,
        ?int $terceroId,
    ): string {
        $ruta = $tipoViaje === TipoViaje::Retiro
            ? "obra {$obraId} → botadero {$botaderoId}"
            : "cantera {$canteraId} → obra {$obraId}";

        $con = $terceroId !== null ? " con el tercero {$terceroId}" : '';

        return "No hay tarifa de {$tipo->value} vigente para la ruta {$ruta}{$con}. "
            .'Hay que pactarla antes de registrar viajes por ahí.';
    }
}
