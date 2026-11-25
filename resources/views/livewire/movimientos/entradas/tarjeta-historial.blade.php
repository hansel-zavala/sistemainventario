@php
    $formatearCantidad = fn (string $cantidad): string => rtrim(rtrim($cantidad, '0'), '.');
    $tipos = \App\Services\MovimientosTable::TYPES;
    $variosArticulos = $movimiento->detalles->count() > 1;
    $articulos = $movimiento->detalles->map(function (\App\Models\MovimientoInventarioDetalle $detalle) use ($tipos, $variosArticulos, $formatearCantidad): array {
        $tipo = $tipos[$detalle->tipo_item] ?? $detalle->tipo_item;

        return [
            'id' => $detalle->id,
            'name' => \App\Services\MovimientosTable::articleName($detalle),
            'subtitle' => $tipo,
            'inlineSubtitle' => $variosArticulos ? $tipo : null,
            'before' => $formatearCantidad($detalle->existencia_anterior),
            'added' => '+ '.$formatearCantidad($detalle->cantidad),
            'after' => $formatearCantidad($detalle->existencia_posterior),
        ];
    });
@endphp

<x-inventory-history-card :id="'entradas-movimiento-'.$movimiento->id" :title="'Entrada #'.$movimiento->id"
    :date="$movimiento->fecha_movimiento" :user="$movimiento->usuario?->name ?? 'Usuario no disponible'"
    :details="$articulos" :notes="$movimiento->observaciones" :single-badge="$articulos->first()['subtitle'] ?? null"
    item-label="Artículo" plural-item-label="artículos" added-label="Entrada" registered-items-label="Artículos registrados"
    empty-message="Esta entrada no tiene artículos registrados."
    header-class="-mx-3 -mt-4 rounded-t-xl border-b border-line bg-line/30 px-3 py-4 sm:-mx-4 sm:px-4"
    :header-detail-toggle="true" :inline-item-limit="2" :detailed-preview="true" />
