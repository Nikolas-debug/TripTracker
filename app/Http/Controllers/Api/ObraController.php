<?php

namespace App\Http\Controllers\Api;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ObraController extends CatalogoController
{
    protected string $modelo = Obra::class;

    protected array $relaciones = ['consorcio'];

    protected array $buscables = ['nombre', 'municipio'];

    protected string $columnaEstado = 'estado';

    protected mixed $valorActivo = 'activa';

    protected mixed $valorInactivo = 'terminada';

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'consorcio_id' => ['required', 'exists:consorcios,id'],
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('obras')->where('consorcio_id', $request->integer('consorcio_id'))->ignore($registro),
            ],
            'direccion' => ['nullable', 'string', 'max:150'],
            'municipio' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', Rule::in(['activa', 'terminada'])],
        ];
    }

    /**
     * Una obra no se desactiva desde aquí: terminarla es un hecho del negocio
     * que se registra cambiando su estado, con todo lo que eso implica para
     * los viajes que todavía cuelgan de ella.
     */
    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Una obra se marca como terminada editándola, no se desactiva.',
        ], 405);
    }
}
