<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El papel firmado por el ingeniero de obra que traen los conductores.
 * Un vale ampara VARIOS viajes; cada viaje se ampara en uno solo.
 * El número es único dentro de la obra, no globalmente.
 */
class Vale extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero', 'obra_id', 'fecha_vale', 'fecha_recepcion',
        'firmado_por', 'recibido_por', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_vale' => 'date',
            'fecha_recepcion' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }
}
