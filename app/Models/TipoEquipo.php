<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TipoEquipo extends Model
{
    use HasFactory;

    protected $table = 'tipos_equipo';

    protected $fillable = [
        'nombre',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'categoria_tipo_equipo', 'tipo_equipo_id', 'categoria_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por'
        );
    }
}
