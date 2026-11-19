<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'aplica_a',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tiposEquipo(): BelongsToMany
    {
        return $this->belongsToMany(TipoEquipo::class, 'categoria_tipo_equipo', 'categoria_id', 'tipo_equipo_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por'
        );
    }
}
