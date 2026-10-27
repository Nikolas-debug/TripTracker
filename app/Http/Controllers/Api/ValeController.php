<?php

namespace App\Http\Controllers\Api;

use App\Actions\Vales\ConfirmarViajesPorVale;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarValeRequest;
use App\Http\Resources\ValeResource;
use App\Http\Resources\ViajeResource;
use App\Models\Vale;
use App\Models\Viaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ValeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $vales = Vale::with(['obra', 'viajes'])
            ->when($request->integer('obra_id'), fn ($q, $id) => $q->where('obra_id', $id))
            ->when($request->integer('numero'), fn ($q, $n) => $q->where('numero', $n))
            ->orderByDesc('fecha_vale')
            ->paginate($request->integer('por_pagina', 25));

        return ValeResource::collection($vales);
    }

    /**
     * Registra el vale y confirma sus viajes en una sola transacción.
     * Si esto se partiera, quedarían viajes confirmados sin respaldo.
     */
    public function store(ConfirmarValeRequest $request, ConfirmarViajesPorVale $confirmar): JsonResponse
    {
        $vale = $confirmar($request->datosVale(), $request->viajes(), $request->user());

        return response()->json([
            'message' => "Vale {$vale->numero} registrado. Se confirmaron {$vale->viajes->count()} viajes.",
            'vale' => new ValeResource($vale->load(self::RELACIONES)),
        ], 201);
    }

    public function show(Vale $vale): ValeResource
    {
        return new ValeResource($vale->load(self::RELACIONES));
    }

    /** Lo que necesita cada viaje para mostrar su ruta completa. */
    private const RELACIONES = [
        'obra', 'viajes.volqueta', 'viajes.obra', 'viajes.botadero',
        'viajes.canteraMaterial.cantera', 'viajes.canteraMaterial.material',
    ];

    /** Los viajes de una obra que todavía esperan vale. */
    public function pendientes(Request $request): AnonymousResourceCollection
    {
        $viajes = Viaje::with(['volqueta', 'obra', 'canteraMaterial.cantera', 'canteraMaterial.material', 'botadero'])
            ->porConfirmar()
            ->when($request->integer('obra_id'), fn ($q, $id) => $q->deLaObra($id))
            ->orderBy('fecha_viaje')
            ->paginate($request->integer('por_pagina', 100));

        return ViajeResource::collection($viajes);
    }
}
