<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipo extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_id',
        'tipo_equipo_id',
        'departamento_id',
        'marca_id',
        'modelo',
        'color',
        'numero_serie',
        'numero_inventario',
        'observaciones',
        'creado_por',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(
            TipoEquipo::class,
            'tipo_equipo_id'
        );
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
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
}