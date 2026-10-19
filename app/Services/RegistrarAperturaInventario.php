<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class RegistrarAperturaInventario
{
    public static function registrar(
        string $tipoItem,
        int $itemId,
        float $cantidadInicial,
        ?string $observaciones = null
    ): void {
        if (! in_array(
            $tipoItem,
            ['herramienta', 'insumo', 'tinta'],
            true
        )) {
            throw new InvalidArgumentException(
                'El tipo de artículo no es válido.'
            );
        }

        if (
            ! is_finite($cantidadInicial)
            || $cantidadInicial < 0
        ) {
            throw new InvalidArgumentException(
                'La existencia inicial no es válida.'
            );
        }

        // Registrar la cabecera del movimiento.
        $movimiento = MovimientoInventario::create([
            'tipo' => 'apertura',
            'fecha_movimiento' => now(),
            'observaciones' => $observaciones
                ?? 'Apertura automática al registrar el artículo.',
            'registrado_por' => Auth::id(),
        ]);

        // Registrar el artículo y su existencia inicial.
        MovimientoInventarioDetalle::create([
            'movimiento_inventario_id' => $movimiento->id,
            'tipo_item' => $tipoItem,
            'item_id' => $itemId,
            'cantidad' => round($cantidadInicial, 2),
            'existencia_anterior' => 0,
            'existencia_posterior' => round(
                $cantidadInicial,
                2
            ),
        ]);
    }
}
