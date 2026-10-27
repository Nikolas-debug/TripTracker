<?php

namespace App\Http\Controllers\Api;

use App\Models\Volqueta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VolquetaController extends CatalogoController
{
    protected string $modelo = Volqueta::class;

    protected array $relaciones = ['propietario', 'conductorHabitual'];

    protected array $buscables = ['placa'];

    protected string $columnaEstado = 'estado';

    protected mixed $valorActivo = 'activa';

    protected mixed $valorInactivo = 'inactiva';

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'placa' => ['required', 'string', 'max:10', Rule::unique('volquetas')->ignore($registro)],
            'propietario_id' => ['required', 'exists:terceros,id'],
            'conductor_habitual_id' => ['nullable', 'exists:terceros,id'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'capacidad_m3' => ['nullable', 'integer', 'min:1', 'max:60'],
            'es_propia' => ['boolean'],
            'estado' => ['nullable', Rule::in(['activa', 'inactiva'])],
        ];
    }
}
