<?php

namespace App\Models;

use App\Enums\TipoTarifa;
use App\Enums\TipoViaje;
use App\Enums\UnidadTarifa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Precio pactado para una RUTA, con vigencia.
 *
 *   suministro -> cantera (origen) + obra (destino)
 *   retiro     -> obra (origen)    + botadero (destino)
 *
 * El material es opcional y solo sirve para excepciones puntuales.
 * Un cambio de precio NUNCA es un UPDATE: se cierra la vigente y se crea otra.
 */
class Tarifa extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo', 'tipo_viaje', 'obra_id', 'cantera_id', 'botadero_id',
        'material_id', 'tercero_id', 'valor', 'valor_base', 'porcentaje',
        'unidad', 'fecha_inicio', 'fecha_fin', 'motivo_cambio', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTarifa::class,
            'tipo_viaje' => TipoViaje::class,
            'unidad' => UnidadTarifa::class,
            'valor' => 'integer',
            'valor_base' => 'integer',
            'porcentaje' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function cantera(): BelongsTo
    {
        return $this->belongsTo(Cantera::class);
    }

    public function botadero(): BelongsTo
    {
        return $this->belongsTo(Botadero::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** El dueño (pago_propietario) o el conductor (pago_conductor). */
    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function scopeVigenteEn(Builder $query, Carbon|string $fecha): Builder
    {
        return $query
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(fn (Builder $q) => $q
                ->whereNull('fecha_fin')
                ->orWhereDate('fecha_fin', '>=', $fecha)
            );
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereNull('fecha_fin');
    }

    public function estaVigente(): bool
    {
        return $this->fecha_fin === null;
    }

    /**
     * Cuánto vale un viaje con esta tarifa.
     * Devuelve pesos enteros: por m³ multiplica, por viaje es plano,
     * y el porcentaje se aplica sobre la base fija de la ruta.
     */
    public function pagoAlConductor(): int
    {
        return (int) round((int) $this->valor_base * ((float) $this->porcentaje / 100));
    }

    public function calcularTotal(int $m3): int
    {
        return match ($this->unidad) {
            UnidadTarifa::M3 => (int) $this->valor * $m3,
            UnidadTarifa::Viaje => (int) $this->valor,
            UnidadTarifa::Porcentaje => (int) round((int) $this->valor_base * ((float) $this->porcentaje / 100)),
        };
    }
}
