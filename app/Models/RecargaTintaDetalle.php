<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecargaTintaDetalle extends Model
{
    use HasFactory;

    protected $table = 'recarga_tinta_detalles';

    protected $fillable = [
        'recarga_tinta_id',
        'tinta_id',
        'porcentaje_antes',
        'porcentaje_agregado',
        'porcentaje_despues',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_antes' => 'decimal:2',
            'porcentaje_agregado' => 'decimal:2',
            'porcentaje_despues' => 'decimal:2',
        ];
    }

    public function recarga(): BelongsTo
    {
        return $this->belongsTo(
            RecargaTinta::class,
            'recarga_tinta_id'
        );
    }

    public function tinta(): BelongsTo
    {
        return $this->belongsTo(Tinta::class);
    }
}