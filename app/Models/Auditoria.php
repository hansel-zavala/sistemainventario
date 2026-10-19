<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'modelo',
        'registro_id',
        'accion',
        'valores_anteriores',
        'valores_nuevos',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'registro_id' => 'integer',
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }
}
