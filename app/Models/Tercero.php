<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una persona. No lleva rol: ser dueño o conductor se deduce de las relaciones.
 */
class Tercero extends Model
{
    use HasFactory;

    protected $table = 'terceros';

    protected $fillable = ['nombre', 'apellido', 'cedula', 'telefono', 'activo'];

    protected $appends = ['nombre_completo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function volquetas(): HasMany
    {
        return $this->hasMany(Volqueta::class, 'propietario_id');
    }

    public function viajesComoConductor(): HasMany
    {
        return $this->hasMany(Viaje::class, 'conductor_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre.' '.$this->apellido);
    }

    /** Terceros que son dueños de al menos una volqueta. */
    public function scopePropietarios(Builder $query): Builder
    {
        return $query->whereHas('volquetas');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
