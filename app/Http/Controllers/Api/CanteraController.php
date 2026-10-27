<?php

namespace App\Http\Controllers\Api;

use App\Models\Cantera;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CanteraController extends CatalogoController
{
    protected string $modelo = Cantera::class;

    protected array $buscables = ['nombre', 'municipio'];

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('canteras')->ignore($registro)],
            'municipio' => ['nullable', 'string', 'max:60'],
            'activo' => ['boolean'],
        ];
    }
}
