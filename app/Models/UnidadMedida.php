<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'unidades_medida';

    protected $fillable = [
        'nombre_singular',
        'nombre_plural',
        'abreviatura',
        'permite_decimales',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'permite_decimales' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'creado_por'
        );
    }
}