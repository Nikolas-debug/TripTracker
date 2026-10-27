<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Qué material vende cada cantera y a qué precio de compra.
 */
class CanteraMaterial extends Model
{
    use HasFactory;

    protected $table = 'cantera_material';

    protected $fillable = ['cantera_id', 'material_id', 'precio_compra_m3', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'precio_compra_m3' => 'integer',
        ];
    }

    public function cantera(): BelongsTo
    {
        return $this->belongsTo(Cantera::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }
}
