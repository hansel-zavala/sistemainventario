<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tinta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'marca_id',
        'color',
        'presentacion',
        'botellas_completas',
        'porcentaje_botella_abierta',
        'stock_minimo_botellas',
        'observaciones',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'botellas_completas' => 'integer',
            'porcentaje_botella_abierta' => 'decimal:2',
            'stock_minimo_botellas' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por'
        );
    }

    public function getExistenciaEquivalenteAttribute(): float
    {
        return
            $this->botellas_completas
            + ((float) $this->porcentaje_botella_abierta / 100);
    }

    public function detallesRecarga(): HasMany
    {
        return $this->hasMany(
            RecargaTintaDetalle::class,
            'tinta_id'
        );
    }
}