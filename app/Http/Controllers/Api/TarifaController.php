<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tarifas\CambiarTarifa;
use App\Actions\Tarifas\ResolverTarifa;
use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Http\Controllers\Controller;
use App\Http\Requests\TarifaRequest;
use App\Http\Resources\TarifaResource;
use App\Models\Tarifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TarifaController extends Controller
{
    private const RELACIONES = ['obra', 'cantera', 'botadero', 'material', 'tercero'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $tarifas = Tarifa::with(self::RELACIONES)
            ->when($request->integer('obra_id'), fn ($q, $id) => $q->where('obra_id', $id))
            ->when($request->input('tipo'), fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($request->boolean('solo_vigentes', true), fn ($q) => $q->abiertas())
            ->orderByDesc('fecha_inicio')
            ->paginate($request->integer('por_pagina', 50));

        return TarifaResource::collection($tarifas);
    }

    public function store(TarifaRequest $request): JsonResponse
    {
        $this->authorize('create', [Tarifa::class, TipoTarifa::from($request->validated('tipo'))]);

        $tarifa = Tarifa::create($request->validated() + ['creado_por' => $request->user()->id]);

        return response()->json(new TarifaResource($tarifa->load(self::RELACIONES)), 201);
    }

    public function show(Tarifa $tarifa): TarifaResource
    {
        return new TarifaResource($tarifa->load(self::RELACIONES));
    }

    /**
     * Cambiar un precio no es editar la fila: cierra la vigente y crea otra.
     */
    public function cambiar(Request $request, Tarifa $tarifa, CambiarTarifa $cambiar): JsonResponse
    {
        $datos = $request->validate([
            'valor' => ['required', 'integer', 'min:0'],
            // Antes del inicio de la vigente, la cerrada quedaría con fin < inicio.
            'desde' => ['required', 'date', 'after:'.$tarifa->fecha_inicio->toDateString()],
            'motivo' => ['nullable', 'string', 'max:150'],
        ]);

        $nueva = $cambiar($tarifa, $datos['valor'], $datos['desde'], $datos['motivo'] ?? null, $request->user());

        return response()->json(new TarifaResource($nueva->load(self::RELACIONES)), 201);
    }

    /**
     * Lo que el formulario de viajes consulta mientras el digitador escribe,
     * para que una tarifa faltante aparezca ANTES de guardar 40 viajes.
     */
    public function resolver(Request $request, ResolverTarifa $resolver): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(TipoTarifa::class)],
            'tipo_viaje' => ['required', Rule::enum(TipoViaje::class)],
            'obra_id' => ['required', 'integer'],
            'cantera_id' => ['nullable', 'integer'],
            'botadero_id' => ['nullable', 'integer'],
            'material_id' => ['nullable', 'integer'],
            'tercero_id' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
        ]);

        $tarifa = $resolver->buscar(
            tipo: TipoTarifa::from($datos['tipo']),
            tipoViaje: TipoViaje::from($datos['tipo_viaje']),
            obraId: $datos['obra_id'],
            canteraId: $datos['cantera_id'] ?? null,
            botaderoId: $datos['botadero_id'] ?? null,
            materialId: $datos['material_id'] ?? null,
            terceroId: $datos['tercero_id'] ?? null,
            fecha: $datos['fecha'] ?? null,
        );

        if ($tarifa === null) {
            return response()->json([
                'encontrada' => false,
                'message' => 'No hay tarifa vigente para esa ruta.',
            ], 200);
        }

        return response()->json([
            'encontrada' => true,
            'tarifa' => new TarifaResource($tarifa->load(self::RELACIONES)),
        ]);
    }
}
