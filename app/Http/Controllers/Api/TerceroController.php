<?php

namespace App\Http\Controllers\Api;

use App\Models\Tercero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TerceroController extends CatalogoController
{
    protected string $modelo = Tercero::class;

    protected array $buscables = ['nombre', 'apellido', 'cedula'];

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['nullable', 'string', 'max:80'],
            'cedula' => ['required', 'string', 'max:15', Rule::unique('terceros')->ignore($registro)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'activo' => ['boolean'],
        ];
    }
}
