<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoTaller extends Model
{
    use HasFactory;

    public const CONDICIONES = ['Nuevo', 'Usado'];

    public const ESTADOS_FISICOS = ['Excelente', 'Bueno', 'Regular', 'Malo'];

    public const DATOS_EQUIPO = ['categoria_alta_id', 'tipo_equipo', 'marca', 'numero_inventario', 'numero_serie', 'modelo', 'color'];

    public const DATOS_FORMULARIO = [
        'fecha_ingreso', 'departamento_id', 'entregado_por',
        ...self::DATOS_EQUIPO,
        'alta_inventario',
        'condicion_equipo', 'estado_fisico', 'observaciones', 'motivo', 'autorizado_por', 'autoriza_respaldo',
    ];

    protected $table = 'ingresos_taller';

    protected $fillable = [
        ...self::DATOS_FORMULARIO,
        'borrador_token', 'tecnico_id', 'tecnico_nombre', 'departamento_nombre', 'categoria_nombre', 'equipo_id', 'equipo_taller_id',
    ];

    protected $attributes = ['version' => 0, 'revision' => 0, 'alta_inventario' => false];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'immutable_datetime',
            'firmado_en' => 'immutable_datetime',
            'autoriza_respaldo' => 'boolean',
            'alta_inventario' => 'boolean',
            'categoria_alta_id' => 'integer',
            'equipo_id' => 'integer',
            'equipo_taller_id' => 'integer',
            'departamento_id' => 'integer',
            'version' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function equipoTaller(): BelongsTo
    {
        return $this->belongsTo(EquipoTaller::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_alta_id');
    }

    public function emitido(): bool
    {
        return $this->folio !== null;
    }

    public function firmado(): bool
    {
        return $this->firmado_en !== null;
    }

    public function estado(): string
    {
        return match (true) {
            $this->firmado() => 'Firmado',
            $this->emitido() => 'Pendiente de firma',
            default => 'Borrador',
        };
    }

    #[Scope]
    protected function historialEquipo(Builder $query, int $equipoId): void
    {
        $query->whereNotNull('folio')
            ->where(fn (Builder $query) => $query->where('equipo_id', $equipoId)
                ->orWhereRelation('equipoTaller', 'equipo_id', $equipoId))
            ->orderByDesc('fecha_ingreso')->orderByDesc('id');
    }
}
