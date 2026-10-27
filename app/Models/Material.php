<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nombre limpio: "relleno", "piedra china". La cantera NO va en el nombre.
 */
class Material extends Model
{
    use HasFactory;

    protected $table = 'materiales';

    protected $fillable = ['nombre', 'unidad', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function canteras(): HasMany
    {
        return $this->hasMany(CanteraMaterial::class);
    }
}
