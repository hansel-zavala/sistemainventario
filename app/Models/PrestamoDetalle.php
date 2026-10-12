<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PrestamoDetalle extends Model
{
    use HasFactory;

    protected $table = 'prestamo_detalles';

    protected $fillable = [
        'prestamo_id',
        'herramienta_id',
        'equipo_id',
        'estado',
        'fecha_devolucion',
        'recibido_por',
        'observaciones_devolucion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_devolucion' => 'datetime',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(
            Prestamo::class,
            'prestamo_id'
        );
    }

    public function herramienta(): BelongsTo
    {
        return $this->belongsTo(
            Herramienta::class,
            'herramienta_id'
        );
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(
            Equipo::class,
            'equipo_id'
        );
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recibido_por'
        );
    }

    public function scopePrestados(Builder $query): Builder
    {
        return $query->where('estado', 'prestado');
    }

    public function getTipoActivoAttribute(): string
    {
        return $this->equipo_id !== null
            ? 'equipo'
            : 'herramienta';
    }
}
