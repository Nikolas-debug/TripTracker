<?php

namespace App\Http\Controllers\Api;

use App\Models\Botadero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BotaderoController extends CatalogoController
{
    protected string $modelo = Botadero::class;

    protected array $buscables = ['nombre', 'municipio'];

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('botaderos')->ignore($registro)],
            'municipio' => ['nullable', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'precio_disposicion_m3' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
    }
}
