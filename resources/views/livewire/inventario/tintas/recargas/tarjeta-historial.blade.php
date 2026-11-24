@php
    $impresora = $recarga->equipo
        ? trim(($recarga->equipo->marca?->nombre ?? '').' '.$recarga->equipo->modelo)
        : 'Impresora no disponible';
    $formatearPorcentaje = fn (string $porcentaje): string => rtrim(rtrim($porcentaje, '0'), '.').' %';
    $articulos = $recarga->detalles->map(fn (\App\Models\RecargaTintaDetalle $detalle): array => [
        'id' => $detalle->id,
        'name' => $detalle->tinta?->nombre ?? 'Tinta no disponible',
        'color' => $detalle->tinta?->color,
        'subtitle' => implode(' · ', array_filter([$detalle->tinta?->color, $detalle->tinta?->marca?->nombre])),
        'singleSubtitle' => $detalle->tinta?->marca?->nombre,
        'before' => $formatearPorcentaje($detalle->porcentaje_antes),
        'added' => '+ '.$formatearPorcentaje($detalle->porcentaje_agregado),
        'after' => $formatearPorcentaje($detalle->porcentaje_despues),
    ]);
@endphp

<x-inventory-history-card :id="'recarga-'.$recarga->id" :title="'Recarga #'.$recarga->id"
    :date="$recarga->created_at" :user="$recarga->usuario?->name ?? 'Usuario no disponible'"
    :details="$articulos" :notes="$recarga->observaciones" item-label="Tinta" plural-item-label="tintas"
    user-label="Técnico" added-label="Agregado" registered-items-label="Tintas registradas"
    empty-message="Esta recarga no tiene tintas registradas.">
    <x-slot:metadata>
        <p class="w-full text-xs text-muted wrap-anywhere">
            <span class="font-medium text-ink">{{ $impresora }}</span>
            @if ($recarga->equipo?->numero_inventario)
                <span class="mx-1" aria-hidden="true">·</span> Inventario {{ $recarga->equipo->numero_inventario }}
            @endif
        </p>
    </x-slot:metadata>
</x-inventory-history-card>
