<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipoTaller extends Model
{
    use HasFactory;

    protected $table = 'equipos_taller';

    protected $fillable = ['equipo_id'];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(IngresoTaller::class);
    }
}
