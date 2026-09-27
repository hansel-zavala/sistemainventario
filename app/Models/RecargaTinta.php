<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecargaTinta extends Model
{
    use HasFactory;

    protected $table = 'recargas_tinta';

    protected $fillable = [
        'equipo_id',
        'observaciones',
        'realizado_por',
    ];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'realizado_por'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            RecargaTintaDetalle::class,
            'recarga_tinta_id'
        );
    }
}