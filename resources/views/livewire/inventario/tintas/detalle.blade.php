@php
    $formatearCantidad = fn ($cantidad) => rtrim(rtrim(number_format((float) $cantidad, 2, '.', ''), '0'), '.');
    $formatearBotellas = fn ($cantidad) => $formatearCantidad($cantidad).((float) $cantidad === 1.0 ? ' botella' : ' botellas');
    $secciones = [
        'Identificación de la tinta' => [
            'Nombre o referencia' => $tinta->nombre,
            'Marca' => $tinta->marca?->nombre,
            'Color' => $tinta->color,
            'Presentación' => $tinta->presentacion,
        ],
        'Existencias y observaciones' => [
            'Botellas completas' => $tinta->botellas_completas,
            'Contenido de botella abierta' => $formatearCantidad($tinta->porcentaje_botella_abierta).' %',
            'Existencia equivalente' => $formatearBotellas($tinta->existencia_equivalente),
            'Stock mínimo' => $formatearBotellas($tinta->stock_minimo_botellas),
        ],
        'Registro' => [
            'Registrada por' => $tinta->creador?->name,
            'Fecha de registro' => $tinta->created_at?->format('d/m/Y H:i'),
            'Última actualización' => $tinta->updated_at?->format('d/m/Y H:i'),
        ],
    ];
@endphp

<x-inventory-detail-dialog id="tinta-detalle" wire:key="detalle-tinta-{{ $tinta->id }}"
    trigger="ver-tinta-{{ $tinta->id }}" label="Ficha de la tinta" icon="drop" :show-technical-history="false"
    :title="$tinta->nombre" :description="implode(' · ', array_filter([$tinta->marca?->nombre, $tinta->color]))"
    :badge="$tinta->activo ? 'Activa' : 'Inactiva'" :badge-tone="$tinta->activo ? 'positive' : 'neutral'"
    edit-action="editarTinta({{ $tinta->id }})" edit-label="Editar tinta">
    <x-slot:general>
        @foreach ($secciones as $seccion => $campos)
            <section class="py-4 sm:py-5">
                <h3 class="mb-3 text-sm font-semibold">{{ $seccion }}</h3>
                <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                    @foreach ($campos as $etiqueta => $valor)
                        <div class="min-w-0">
                            <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                            <dd class="mt-0.5 text-sm font-medium wrap-anywhere">{{ filled($valor) ? $valor : 'Sin registrar' }}</dd>
                            @if ($etiqueta === 'Existencia equivalente' && $tinta->existencia_equivalente <= (float) $tinta->stock_minimo_botellas)
                                <dd class="mt-1 text-xs font-medium text-red-700">Stock bajo</dd>
                            @endif
                        </div>
                    @endforeach
                    @if ($seccion === 'Existencias y observaciones')
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-muted">Observaciones</dt>
                            <dd @class(['mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap', 'text-muted italic' => blank($tinta->observaciones)])>{{ filled($tinta->observaciones) ? $tinta->observaciones : 'Sin observaciones registradas.' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endforeach
    </x-slot:general>

    <x-slot:movimientos>
        <x-inventory-stock-movements :movimientos="$movimientos" id="tinta-movimiento" :show-unit="true"
            empty-message="Esta tinta aún no tiene movimientos registrados." pagination-label="Paginación de movimientos de la tinta" />
    </x-slot:movimientos>
</x-inventory-detail-dialog>
