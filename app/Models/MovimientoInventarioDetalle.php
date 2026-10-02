<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventarioDetalle extends Model
{
    use HasFactory;

    protected $table = 'movimiento_inventario_detalles';

    protected $fillable = [
        'movimiento_inventario_id',
        'tipo_item',
        'item_id',
        'cantidad',
        'existencia_anterior',
        'existencia_posterior',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'existencia_anterior' => 'decimal:2',
            'existencia_posterior' => 'decimal:2',
        ];
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(
            MovimientoInventario::class,
            'movimiento_inventario_id'
        );
    }

    public function obtenerItem()
    {
        return match ($this->tipo_item) {
            'herramienta' =>
                Herramienta::find($this->item_id),

            'insumo' =>
                Insumo::find($this->item_id),

            'tinta' =>
                Tinta::find($this->item_id),

            default => null,
        };
    }
}