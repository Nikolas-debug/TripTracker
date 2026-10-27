<?php

namespace App\Http\Controllers\Api;

use App\Actions\Liquidaciones\CerrarLiquidacion;
use App\Actions\Liquidaciones\GenerarLiquidacion;
use App\Actions\Liquidaciones\RegistrarAnticipo;
use App\Enums\TipoLiquidacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnticipoRequest;
use App\Http\Requests\GenerarLiquidacionRequest;
use App\Http\Resources\LiquidacionResource;
use App\Models\Anticipo;
use App\Models\Liquidacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LiquidacionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $liquidaciones = Liquidacion::with(['consorcio', 'tercero', 'cantera', 'anticipos'])
            ->when($request->input('tipo'), fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha_fin')
            ->paginate($request->integer('por_pagina', 25));

        return LiquidacionResource::collection($liquidaciones);
    }

    public function store(GenerarLiquidacionRequest $request, GenerarLiquidacion $generar): JsonResponse
    {
        $liquidacion = $generar(
            TipoLiquidacion::from($request->input('tipo')),
            $request->input('consorcio_id'),
            $request->input('tercero_id'),
            $request->input('desde'),
            $request->input('hasta'),
            $request->input('cantera_id'),
        );

        return response()->json(
            new LiquidacionResource($liquidacion->load(['consorcio', 'tercero', 'cantera', 'anticipos'])),
            201
        );
    }

    public function show(Liquidacion $liquidacion): LiquidacionResource
    {
        return new LiquidacionResource(
            $liquidacion->load(['consorcio', 'tercero', 'cantera', 'anticipos'])->cargarViajes([
                'volqueta', 'obra', 'canteraMaterial.cantera', 'canteraMaterial.material', 'botadero',
            ])
        );
    }

    public function cerrar(Request $request, Liquidacion $liquidacion, CerrarLiquidacion $cerrar): LiquidacionResource
    {
        $this->authorize('cerrar', $liquidacion);

        return new LiquidacionResource(
            $cerrar($liquidacion, $request->user())->load(['consorcio', 'tercero', 'cantera', 'anticipos'])
        );
    }

    public function agregarAnticipo(AnticipoRequest $request, Liquidacion $liquidacion, RegistrarAnticipo $registrar): JsonResponse
    {
        $registrar($liquidacion, $request->validated(), $request->user());

        return response()->json(
            new LiquidacionResource($liquidacion->fresh()->load(['consorcio', 'tercero', 'cantera', 'anticipos'])),
            201
        );
    }

    public function eliminarAnticipo(Anticipo $anticipo): JsonResponse
    {
        $liquidacion = $anticipo->liquidacion;

        abort_unless($liquidacion->estaAbierta(), 409, 'No se pueden quitar anticipos de una cuenta cerrada.');

        $anticipo->delete();
        $liquidacion->load('anticipos')->cargarViajes()->recalcularTotales()->save();

        return response()->json(new LiquidacionResource($liquidacion->load(['consorcio', 'tercero', 'cantera', 'anticipos'])));
    }
}
