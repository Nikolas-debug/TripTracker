<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Reportes\ConsultasPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El panel del administrador. Un solo endpoint para todo el tablero: son seis
 * consultas contra el mismo periodo, y partirlas en seis llamadas solo hace
 * que los números de la pantalla puedan venir de instantes distintos.
 */
class PanelController extends Controller
{
    public function __invoke(Request $request, ConsultasPanel $panel): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $hasta = Carbon::parse($datos['hasta'] ?? now());
        $desde = Carbon::parse($datos['desde'] ?? $hasta->copy()->subDays(29));

        // El periodo anterior, del mismo largo, para poder decir "vs. antes".
        $dias = $desde->diffInDays($hasta) + 1;
        $antesHasta = $desde->copy()->subDay();
        $antesDesde = $antesHasta->copy()->subDays($dias - 1);

        $d = $desde->toDateString();
        $h = $hasta->toDateString();

        return response()->json([
            'periodo' => ['desde' => $d, 'hasta' => $h, 'dias' => $dias],
            'resumen' => $panel->resumen($d, $h),
            'resumen_anterior' => $panel->resumen($antesDesde->toDateString(), $antesHasta->toDateString()),
            'serie' => $panel->serieSemanal($d, $h),
            'margen_por_obra' => $panel->margenPorObra($d, $h),
            'rendimiento' => $panel->rendimientoPorVolqueta($d, $h),
            'cartera' => $panel->cartera(),
            'desviaciones' => $panel->desviaciones($d, $h),
        ]);
    }
}
