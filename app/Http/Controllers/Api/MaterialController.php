<?php

namespace App\Http\Controllers\Api;

use App\Models\Material;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialController extends CatalogoController
{
    protected string $modelo = Material::class;

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            // Nombre limpio: la cantera va en su tabla, no dentro del nombre.
            'nombre' => ['required', 'string', 'max:120', Rule::unique('materiales')->ignore($registro)],
            'unidad' => ['nullable', 'string', 'max:10'],
            'activo' => ['boolean'],
        ];
    }
}
