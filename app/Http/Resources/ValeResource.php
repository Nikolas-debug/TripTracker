<?php

namespace App\Http\Resources;

use App\Models\Vale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vale */
class ValeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'obra' => $this->whenLoaded('obra', fn () => $this->obra->nombre),
            'fecha_vale' => $this->fecha_vale?->toDateString(),
            'fecha_recepcion' => $this->fecha_recepcion?->toDateString(),
            'firmado_por' => $this->firmado_por,
            'observacion' => $this->observacion,
            'viajes' => ViajeResource::collection($this->whenLoaded('viajes')),
            'total_viajes' => $this->whenLoaded('viajes', fn () => $this->viajes->count()),
        ];
    }
}
