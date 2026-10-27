<?php

namespace App\Http\Controllers\Api;

use App\Models\CanteraMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class CanteraMaterialController extends CatalogoController
{
    protected string $modelo = CanteraMaterial::class;

    protected array $relaciones = ['cantera', 'material'];

    protected function reglas(Request $request, ?Model $registro = null): array
    {
        return [
            'cantera_id' => ['required', 'exists:canteras,id'],
            'material_id' => [
                'required', 'exists:materiales,id',
                Rule::unique('cantera_material')->where('cantera_id', $request->integer('cantera_id'))->ignore($registro),
            ],
            'precio_compra_m3' => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /** Aquí no se busca por nombre sino por cantera. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CanteraMaterial::with($this->relaciones)
            ->when($request->integer('cantera_id'), fn ($q, $id) => $q->where('cantera_id', $id))
            ->when($request->boolean('solo_activos'), fn ($q) => $q->where('activo', true));

        return JsonResource::collection($query->paginate($request->integer('por_pagina', 50)));
    }
}
