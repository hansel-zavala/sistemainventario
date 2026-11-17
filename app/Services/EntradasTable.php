<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Database\Eloquent\Builder;

class EntradasTable extends MovimientosTable
{
    public const COLUMNS = [
        'entrada' => 'N.º de entrada', 'fecha' => 'Fecha del movimiento', 'usuario' => 'Registrado por',
        'tipo' => 'Tipo de artículo', 'articulo' => 'Artículo', 'anterior' => 'Existencia anterior',
        'cantidad' => 'Cantidad ingresada', 'posterior' => 'Existencia posterior', 'observaciones' => 'Observaciones',
    ];

    public function query(array $filters): Builder
    {
        return $this->movementQuery('entrada', $filters);
    }

    protected function exportValues(MovimientoInventario $movement, MovimientoInventarioDetalle $detail): array
    {
        return ['entrada' => (string) $movement->id, ...parent::exportValues($movement, $detail)];
    }
}
