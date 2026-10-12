<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'departamento_destino_id',
        'persona_recibe',
        'fecha_prestamo',
        'fecha_prevista_devolucion',
        'estado',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_prestamo' => 'datetime',
            'fecha_prevista_devolucion' => 'datetime',
        ];
    }

    public function departamentoDestino(): BelongsTo
    {
        return $this->belongsTo(
            Departamento::class,
            'departamento_destino_id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registrado_por'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            PrestamoDetalle::class,
            'prestamo_id'
        );
    }

    public function getEstaVencidoAttribute(): bool
    {
        return $this->estado === 'activo'
            && $this->fecha_prevista_devolucion->isPast();
    }
}
