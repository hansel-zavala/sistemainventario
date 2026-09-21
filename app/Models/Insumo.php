<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insumo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'cantidad_actual',
        'stock_minimo',
        'observaciones',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(
            UnidadMedida::class,
            'unidad_medida_id'
        );
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por'
        );
    }
}