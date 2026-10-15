<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoDetalle extends Model
{
    use HasFactory;

    protected $table = 'prestamo_detalles';

    protected $fillable = [
        'prestamo_id',
        'herramienta_id',
        'equipo_id',
        'cantidad_prestada',
        'cantidad_devuelta',
        'estado',
        'fecha_devolucion',
        'recibido_por',
        'observaciones_devolucion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_prestada' => 'decimal:2',
            'cantidad_devuelta' => 'decimal:2',
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

    public function devoluciones(): HasMany
    {
        return $this->hasMany(
            PrestamoDevolucion::class,
            'prestamo_detalle_id'
        );
    }

    public function getCantidadPendienteAttribute(): float
    {
        return max(
            0,
            round(
                (float) $this->cantidad_prestada
                - (float) $this->cantidad_devuelta,
                2
            )
        );
    }
}
