<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoDevolucion extends Model
{
    protected $table = 'prestamo_devoluciones';

    protected $fillable = [
        'prestamo_detalle_id',
        'cantidad',
        'fecha_devolucion',
        'recibido_por',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha_devolucion' => 'datetime',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(
            PrestamoDetalle::class,
            'prestamo_detalle_id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recibido_por'
        );
    }
}
