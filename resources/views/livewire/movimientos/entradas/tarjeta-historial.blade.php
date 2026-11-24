@php
    $formatearCantidad = fn (string $cantidad): string => rtrim(rtrim($cantidad, '0'), '.');
    $tipos = \App\Services\MovimientosTable::TYPES;
    $articulos = $movimiento->detalles->map(fn (\App\Models\MovimientoInventarioDetalle $detalle): array => [
        'id' => $detalle->id,
        'name' => \App\Services\MovimientosTable::articleName($detalle),
        'subtitle' => $tipos[$detalle->tipo_item] ?? $detalle->tipo_item,
        'before' => $formatearCantidad($detalle->existencia_anterior),
        'added' => '+ '.$formatearCantidad($detalle->cantidad),
        'after' => $formatearCantidad($detalle->existencia_posterior),
    ]);
@endphp

<x-inventory-history-card :id="'entradas-movimiento-'.$movimiento->id" :title="'Entrada #'.$movimiento->id"
    :date="$movimiento->fecha_movimiento" :user="$movimiento->usuario?->name ?? 'Usuario no disponible'"
    :details="$articulos" :notes="$movimiento->observaciones" :single-badge="$articulos->first()['subtitle'] ?? null"
    item-label="Artículo" plural-item-label="artículos" added-label="Entrada" registered-items-label="Artículos registrados"
    empty-message="Esta entrada no tiene artículos registrados." />
