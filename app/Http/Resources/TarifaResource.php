<?php

namespace App\Http\Resources;

use App\Enums\TipoViaje;
use App\Models\Tarifa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tarifa */
class TarifaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_viaje' => $this->tipo_viaje,
            'ruta' => $this->ruta(),
            'material' => $this->whenLoaded('material', fn () => $this->material?->nombre),
            'tercero' => $this->whenLoaded('tercero', fn () => $this->tercero?->nombre_completo),
            'valor' => $this->valor,
            'valor_base' => $this->valor_base,
            'porcentaje' => $this->porcentaje,
            'unidad' => $this->unidad,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'vigente' => $this->estaVigente(),
            'motivo_cambio' => $this->motivo_cambio,
        ];
    }

    private function ruta(): ?string
    {
        if (! $this->relationLoaded('obra')) {
            return null;
        }

        return $this->tipo_viaje === TipoViaje::Retiro
            ? "{$this->obra->nombre} → ".($this->botadero?->nombre ?? '?')
            : ($this->cantera?->nombre ?? '?')." → {$this->obra->nombre}";
    }
}
