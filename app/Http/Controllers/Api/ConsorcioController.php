<?php

namespace App\Http\Controllers\Api;

use App\Models\Consorcio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsorcioController extends CatalogoController
{
    protected string $modelo = Consorcio::class;

    protected array $buscables = ['razon_social', 'nit'];

    protected string $columnaEstado = 'estado_contrato';

    protected mixed $valorActivo = 'activo';

    protected mixed $valorInactivo = 'terminado';

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'max:20', Rule::unique('consorcios')->ignore($registro)],
            'pais' => ['nullable', 'string', 'max:60'],
            'departamento' => ['nullable', 'string', 'max:60'],
            'municipio' => ['nullable', 'string', 'max:60'],
            'direccion_principal' => ['nullable', 'string', 'max:150'],
            'correo' => ['nullable', 'email', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'estado_contrato' => ['nullable', Rule::in(['activo', 'suspendido', 'terminado'])],
        ];
    }
}
