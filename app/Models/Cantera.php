<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cantera extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'municipio', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(CanteraMaterial::class);
    }
}
