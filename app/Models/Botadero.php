<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sitio de disposición de escombro: el destino de los viajes de retiro.
 */
class Botadero extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'municipio', 'direccion', 'precio_disposicion_m3', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'precio_disposicion_m3' => 'integer',
        ];
    }

    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }
}
