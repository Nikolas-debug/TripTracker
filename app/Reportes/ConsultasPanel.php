<?php

namespace App\Reportes;

use App\Enums\EstadoLiquidacion;
use App\Enums\EstadoViaje;
use App\Enums\TipoLiquidacion;
use App\Models\Liquidacion;
use App\Models\Viaje;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras del panel del administrador.
 *
 * Todo sale de los valores CONGELADOS en cada viaje, nunca de la tabla de
 * tarifas: por eso un cambio de precio no reescribe el histórico. Las tres
 * expresiones de abajo son la traducción a SQL de las reglas del modelo.
 */
class ConsultasPanel
{
    /** Los m³ que mandan: los del vale si ya se confirmó, si no los registrados. */
    private const M3 = 'COALESCE(m3_confirmado, m3_registrado)';

    /** El material: se le cobra al consorcio y se le paga igual a la cantera. */
    private const MATERIAL = 'COALESCE(valor_material_m3, 0) * COALESCE(m3_confirmado, m3_registrado)';

    /** El flete de la tarifa de cobro. */
    private const FLETE = "CASE WHEN unidad_cobro = 'm3'
                                THEN valor_cobro_unitario * COALESCE(m3_confirmado, m3_registrado)
                                ELSE valor_cobro_unitario END";

    /** Lo que se le cobra al consorcio por el viaje: flete más material. */
    private const COBRO = self::FLETE.' + '.self::MATERIAL;

    /** El margen: el material entra y sale igual, así que es el flete menos lo pagado. */
    private const MARGEN = 'COALESCE(SUM('.self::FLETE.'), 0) - COALESCE(SUM('.self::PAGO.'), 0)';

    /**
     * Lo que sale por ese viaje: al dueño de la volqueta, o al conductor
     * cuando la volqueta es propia. Nunca los dos a la vez, pero se suman
     * igual para no depender de esa invariante.
     */
    private const PAGO = "COALESCE(CASE WHEN unidad_pago = 'm3'
                                       THEN valor_pago_unitario * COALESCE(m3_confirmado, m3_registrado)
                                       ELSE valor_pago_unitario END, 0)
                          + COALESCE(valor_pago_conductor, 0)";

    /**
     * Los totales del periodo. Solo cuentan los viajes confirmados: un viaje
     * sin vale todavía no es plata, es una expectativa.
     */
    public function resumen(string $desde, string $hasta): array
    {
        $fila = $this->confirmados($desde, $hasta)
            ->selectRaw('COUNT(*) AS viajes')
            ->selectRaw('COALESCE(SUM('.self::M3.'), 0) AS m3')
            ->selectRaw('COALESCE(SUM('.self::MATERIAL.'), 0) AS material')
            ->selectRaw('COALESCE(SUM('.self::COBRO.'), 0) AS cobrado')
            ->selectRaw('COALESCE(SUM('.self::PAGO.'), 0) AS pagado')
            ->first();

        $cobrado = (int) $fila->cobrado;
        $pagado = (int) $fila->pagado;
        $material = (int) $fila->material;

        return [
            'viajes' => (int) $fila->viajes,
            'm3' => (int) $fila->m3,
            'cobrado' => $cobrado,
            'material' => $material,
            'pagado' => $pagado,
            'margen' => $cobrado - $material - $pagado,
        ];
    }

    /**
     * La serie por semana. Se agrupa en el servidor y no en el navegador
     * porque un año de viajes son miles de filas que nadie necesita traer.
     */
    public function serieSemanal(string $desde, string $hasta): array
    {
        return $this->confirmados($desde, $hasta)
            ->selectRaw("TO_CHAR(DATE_TRUNC('week', fecha_viaje), 'YYYY-MM-DD') AS semana")
            ->selectRaw('COUNT(*) AS viajes')
            ->selectRaw('COALESCE(SUM('.self::MATERIAL.'), 0) AS material')
            ->selectRaw('COALESCE(SUM('.self::COBRO.'), 0) AS cobrado')
            ->selectRaw('COALESCE(SUM('.self::PAGO.'), 0) AS pagado')
            ->groupBy('semana')
            ->orderBy('semana')
            ->get()
            ->map(fn ($f) => [
                'semana' => $f->semana,
                'viajes' => (int) $f->viajes,
                'cobrado' => (int) $f->cobrado,
                'pagado' => (int) $f->pagado,
                'material' => (int) $f->material,
                'margen' => (int) $f->cobrado - (int) $f->material - (int) $f->pagado,
            ])
            ->all();
    }

    /** Dónde se está ganando: margen por obra, de mayor a menor. */
    public function margenPorObra(string $desde, string $hasta, int $limite = 8): array
    {
        return $this->confirmados($desde, $hasta)
            ->join('obras', 'obras.id', '=', 'viajes.obra_id')
            ->join('consorcios', 'consorcios.id', '=', 'obras.consorcio_id')
            ->selectRaw('obras.id AS obra_id')
            ->selectRaw('obras.nombre AS obra')
            ->selectRaw('consorcios.razon_social AS consorcio')
            ->selectRaw('COUNT(*) AS viajes')
            ->selectRaw('COALESCE(SUM('.self::COBRO.'), 0) AS cobrado')
            ->selectRaw('COALESCE(SUM('.self::MATERIAL.'), 0) AS material')
            ->selectRaw('COALESCE(SUM('.self::PAGO.'), 0) AS pagado')
            ->selectRaw(self::MARGEN.' AS margen')
            ->groupBy('obras.id', 'obras.nombre', 'consorcios.razon_social')
            ->orderByDesc('margen')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'obra_id' => (int) $f->obra_id,
                'obra' => $f->obra,
                'consorcio' => $f->consorcio,
                'viajes' => (int) $f->viajes,
                'cobrado' => (int) $f->cobrado,
                'material' => (int) $f->material,
                'pagado' => (int) $f->pagado,
                'margen' => (int) $f->margen,
            ])
            ->all();
    }

    /** Cuánto mueve cada volqueta. Ahora que el pago es por viaje, importa. */
    public function rendimientoPorVolqueta(string $desde, string $hasta, int $limite = 10): array
    {
        return $this->confirmados($desde, $hasta)
            ->join('volquetas', 'volquetas.id', '=', 'viajes.volqueta_id')
            ->leftJoin('terceros', 'terceros.id', '=', 'volquetas.propietario_id')
            ->selectRaw('volquetas.placa')
            ->selectRaw('volquetas.es_propia')
            ->selectRaw("TRIM(CONCAT(terceros.nombre, ' ', COALESCE(terceros.apellido, ''))) AS propietario")
            ->selectRaw('COUNT(*) AS viajes')
            ->selectRaw('COALESCE(SUM('.self::M3.'), 0) AS m3')
            ->selectRaw('COALESCE(SUM('.self::PAGO.'), 0) AS pagado')
            ->groupBy('volquetas.placa', 'volquetas.es_propia', 'terceros.nombre', 'terceros.apellido')
            ->orderByDesc('viajes')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'placa' => $f->placa,
                'es_propia' => (bool) $f->es_propia,
                'propietario' => $f->propietario,
                'viajes' => (int) $f->viajes,
                'm3' => (int) $f->m3,
                'pagado' => (int) $f->pagado,
            ])
            ->all();
    }

    /**
     * Qué se debe y qué deben. Dos fuentes distintas: las cuentas abiertas
     * (ya liquidadas, esperando pago) y los viajes confirmados que todavía no
     * entran en ninguna cuenta.
     */
    public function cartera(): array
    {
        $cuentas = Liquidacion::query()
            ->with(['consorcio:id,razon_social', 'tercero:id,nombre,apellido'])
            ->where('estado', '!=', EstadoLiquidacion::Pagada)
            // Las de material nacen pagadas en la cantera: no son cartera.
            ->where('tipo', '!=', TipoLiquidacion::Material)
            ->orderByDesc('saldo')
            ->get()
            ->map(fn (Liquidacion $l) => [
                'id' => $l->id,
                'tipo' => $l->tipo->value,
                'numero_cuenta' => $l->numero_cuenta,
                'destinatario' => $l->esCobro()
                    ? $l->consorcio?->razon_social
                    : trim($l->tercero?->nombre.' '.$l->tercero?->apellido),
                'estado' => $l->estado->value,
                'saldo' => (int) $l->saldo,
            ]);

        $sinLiquidar = Viaje::query()
            ->where('estado', EstadoViaje::Confirmado)
            ->selectRaw('COUNT(*) FILTER (WHERE liquidacion_cobro_id IS NULL) AS viajes_sin_cobrar')
            ->selectRaw('COUNT(*) FILTER (WHERE liquidacion_pago_id IS NULL) AS viajes_sin_pagar')
            ->selectRaw('COALESCE(SUM('.self::COBRO.') FILTER (WHERE liquidacion_cobro_id IS NULL), 0) AS por_cobrar')
            ->selectRaw('COALESCE(SUM('.self::PAGO.') FILTER (WHERE liquidacion_pago_id IS NULL), 0) AS por_pagar')
            ->first();

        return [
            'cuentas' => $cuentas->all(),
            'por_cobrar_en_cuentas' => (int) $cuentas->where('tipo', TipoLiquidacion::Cobro->value)->sum('saldo'),
            'por_pagar_en_cuentas' => (int) $cuentas->where('tipo', TipoLiquidacion::Pago->value)->sum('saldo'),
            'sin_liquidar' => [
                'viajes_sin_cobrar' => (int) $sinLiquidar->viajes_sin_cobrar,
                'viajes_sin_pagar' => (int) $sinLiquidar->viajes_sin_pagar,
                'por_cobrar' => (int) $sinLiquidar->por_cobrar,
                'por_pagar' => (int) $sinLiquidar->por_pagar,
            ],
        ];
    }

    /**
     * Lo que hay que vigilar: dónde el vale dijo otra cosa, qué se anuló y
     * cuánto tardan los vales en llegar. La diferencia entre m³ registrados y
     * confirmados es la señal más útil que tiene el administrador.
     */
    public function desviaciones(string $desde, string $hasta): array
    {
        $ajustados = Viaje::query()
            ->with(['volqueta:id,placa', 'obra:id,nombre', 'vale:id,numero'])
            ->where('estado', EstadoViaje::Confirmado)
            ->whereBetween('fecha_viaje', [$desde, $hasta])
            ->whereColumn('m3_confirmado', '!=', 'm3_registrado')
            ->orderByRaw('ABS(m3_registrado - m3_confirmado) DESC')
            ->limit(20)
            ->get()
            ->map(fn (Viaje $v) => [
                'id' => $v->id,
                'fecha' => $v->fecha_viaje?->toDateString(),
                'placa' => $v->volqueta?->placa,
                'obra' => $v->obra?->nombre,
                'vale' => $v->vale?->numero,
                'm3_registrado' => $v->m3_registrado,
                'm3_confirmado' => $v->m3_confirmado,
                'diferencia' => $v->m3_confirmado - $v->m3_registrado,
            ]);

        $totales = Viaje::query()
            ->whereBetween('fecha_viaje', [$desde, $hasta])
            ->selectRaw("COUNT(*) FILTER (WHERE estado = 'anulado') AS anulados")
            ->selectRaw("COUNT(*) FILTER (WHERE estado = 'por_confirmar') AS por_confirmar")
            ->selectRaw("COUNT(*) FILTER (WHERE estado = 'confirmado' AND m3_confirmado <> m3_registrado) AS ajustados")
            ->selectRaw("COALESCE(SUM(".self::M3.") FILTER (WHERE estado = 'confirmado' AND m3_confirmado <> m3_registrado), 0) AS m3_ajustados")
            ->first();

        // Cuánto lleva esperando vale el viaje más viejo de cada obra.
        $espera = Viaje::query()
            ->join('obras', 'obras.id', '=', 'viajes.obra_id')
            ->where('viajes.estado', EstadoViaje::PorConfirmar)
            ->selectRaw('obras.nombre AS obra')
            ->selectRaw('COUNT(*) AS viajes')
            ->selectRaw('MIN(viajes.fecha_viaje) AS mas_antiguo')
            ->selectRaw('CURRENT_DATE - MIN(viajes.fecha_viaje) AS dias')
            ->groupBy('obras.nombre')
            ->orderByDesc('dias')
            ->get()
            ->map(fn ($f) => [
                'obra' => $f->obra,
                'viajes' => (int) $f->viajes,
                'mas_antiguo' => $f->mas_antiguo,
                'dias' => (int) $f->dias,
            ]);

        return [
            'ajustados' => $ajustados->all(),
            'espera_de_vales' => $espera->all(),
            'totales' => [
                'anulados' => (int) $totales->anulados,
                'por_confirmar' => (int) $totales->por_confirmar,
                'ajustados' => (int) $totales->ajustados,
                'm3_ajustados' => (int) $totales->m3_ajustados,
            ],
        ];
    }

    private function confirmados(string $desde, string $hasta): QueryBuilder
    {
        return DB::table('viajes')
            ->where('viajes.estado', EstadoViaje::Confirmado->value)
            ->whereBetween('viajes.fecha_viaje', [$desde, $hasta]);
    }
}
