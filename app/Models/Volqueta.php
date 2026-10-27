<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Volqueta extends Model
{
    use HasFactory;

    protected $fillable = [
        'placa', 'propietario_id', 'conductor_habitual_id',
        'tipo', 'capacidad_m3', 'es_propia', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_propia' => 'boolean',
            'capacidad_m3' => 'integer',
        ];
    }

    /** A quien se le liquidan los viajes de esta volqueta. */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'propietario_id');
    }

    /** Solo para autocompletar el formulario de viajes. */
    public function conductorHabitual(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'conductor_habitual_id');
    }

    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }
}
