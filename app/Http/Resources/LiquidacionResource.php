<?php

namespace App\Http\Resources;

use App\Enums\TipoLiquidacion;
use App\Models\Liquidacion;
use App\Support\NumeroEnLetras;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Liquidacion */
class LiquidacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'numero_cuenta' => $this->numero_cuenta,
            'destinatario' => match ($this->tipo) {
                TipoLiquidacion::Cobro => $this->whenLoaded('consorcio', fn () => $this->consorcio?->razon_social),
                TipoLiquidacion::Pago => $this->whenLoaded('tercero', fn () => $this->tercero?->nombre_completo),
                TipoLiquidacion::Material => $this->whenLoaded('cantera', fn () => $this->cantera?->nombre),
            },
            // Para la cuenta de cobro impresa.
            'nit_destinatario' => $this->when($this->esCobro() && $this->relationLoaded('consorcio'), fn () => $this->consorcio?->nit),
            'total_en_letras' => $this->when($this->esCobro(), fn () => NumeroEnLetras::pesos($this->total_bruto)),
            'periodo' => [
                'desde' => $this->fecha_inicio?->toDateString(),
                'hasta' => $this->fecha_fin?->toDateString(),
            ],
            'total_bruto' => $this->total_bruto,
            'total_anticipos' => $this->total_anticipos,
            'saldo' => $this->saldo,
            'estado' => $this->estado,
            'total_viajes' => $this->whenLoaded('viajes', fn () => $this->viajes->count()),
            'viajes' => ViajeResource::collection($this->whenLoaded('viajes')),
            'anticipos' => $this->whenLoaded('anticipos', fn () => $this->anticipos->map(fn ($a) => [
                'id' => $a->id,
                'fecha' => $a->fecha?->toDateString(),
                'valor' => $a->valor,
                'observacion' => $a->observacion,
            ])),
            'cerrada_en' => $this->cerrada_en?->toIso8601String(),
        ];
    }
}
