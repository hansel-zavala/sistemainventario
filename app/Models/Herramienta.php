<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Herramienta extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'tipo_control',
        'codigo_interno',
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

    public function prestamos(): HasMany
    {
        return $this->hasMany(
            PrestamoDetalle::class,
            'herramienta_id'
        );
    }

    public function getCantidadPrestadaAttribute(): float
    {
        if ($this->tipo_control === 'individual') {
            return $this->prestamos()
                ->where('estado', 'prestado')
                ->exists() ? 1.0 : 0.0;
        }

        $detalles = $this->prestamos()
            ->where('estado', 'prestado')
            ->get([
                'cantidad_prestada',
                'cantidad_devuelta',
            ]);

        return round(
            $detalles->sum(
                fn ($detalle) => $detalle->cantidad_pendiente
            ),
            2
        );
    }

    public function getCantidadDisponibleAttribute(): float
    {
        return max(
            0,
            round(
                (float) $this->cantidad_actual
                - $this->cantidad_prestada,
                2
            )
        );
    }
}
