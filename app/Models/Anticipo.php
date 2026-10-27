<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anticipo extends Model
{
    use HasFactory;

    protected $fillable = ['liquidacion_id', 'fecha', 'valor', 'observacion', 'registrado_por'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'integer',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
