<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consorcio extends Model
{
    use HasFactory;

    protected $fillable = [
        'razon_social', 'nit', 'pais', 'departamento', 'municipio',
        'direccion_principal', 'correo', 'telefono', 'estado_contrato',
    ];

    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }
}
