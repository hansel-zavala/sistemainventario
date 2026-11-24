@php
    $unidad = $insumo->unidadMedida;
    $formatearExistencia = fn ($cantidad) => rtrim(rtrim(number_format((float) $cantidad, 2, '.', ''), '0'), '.').' '.((float) $cantidad === 1.0
        ? $unidad->nombre_singular : $unidad->nombre_plural);
    $secciones = [
        'Identificación del insumo' => [
            'Nombre' => $insumo->nombre,
            'Categoría' => $insumo->categoria->nombre,
            'Marca' => $insumo->marca?->nombre,
            'Unidad de medida' => $unidad->nombre_singular.($unidad->abreviatura ? ' ('.$unidad->abreviatura.')' : ''),
        ],
        'Existencias y observaciones' => [
            'Existencia actual' => $formatearExistencia($insumo->cantidad_actual),
            'Stock mínimo' => $formatearExistencia($insumo->stock_minimo),
        ],
        'Registro' => [
            'Registrado por' => $insumo->creador?->name,
            'Fecha de registro' => $insumo->created_at?->format('d/m/Y H:i'),
            'Última actualización' => $insumo->updated_at?->format('d/m/Y H:i'),
        ],
    ];
@endphp

<x-inventory-detail-dialog id="insumo-detalle" wire:key="detalle-insumo-{{ $insumo->id }}"
    trigger="ver-insumo-{{ $insumo->id }}" label="Ficha del insumo" icon="box" :show-technical-history="false"
    :title="$insumo->nombre" :description="$insumo->categoria->nombre"
    :badge="$insumo->activo ? 'Activo' : 'Inactivo'" :badge-tone="$insumo->activo ? 'positive' : 'neutral'"
    edit-action="editarInsumo({{ $insumo->id }})" edit-label="Editar insumo">
    <x-slot:general>
        @foreach ($secciones as $seccion => $campos)
            <section class="py-4 sm:py-5">
                <h3 class="mb-3 text-sm font-semibold">{{ $seccion }}</h3>
                <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                    @foreach ($campos as $etiqueta => $valor)
                        <div class="min-w-0">
                            <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                            <dd class="mt-0.5 text-sm font-medium wrap-anywhere">{{ filled($valor) ? $valor : 'Sin registrar' }}</dd>
                            @if ($etiqueta === 'Existencia actual' && (float) $insumo->cantidad_actual <= (float) $insumo->stock_minimo)
                                <dd class="mt-1 text-xs font-medium text-red-700">Stock bajo</dd>
                            @endif
                        </div>
                    @endforeach
                    @if ($seccion === 'Existencias y observaciones')
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-muted">Observaciones</dt>
                            <dd @class(['mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap', 'text-muted italic' => blank($insumo->observaciones)])>{{ filled($insumo->observaciones) ? $insumo->observaciones : 'Sin observaciones registradas.' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endforeach
    </x-slot:general>

    <x-slot:movimientos>
        <x-inventory-stock-movements :movimientos="$movimientos" id="insumo-movimiento"
            empty-message="Este insumo aún no tiene movimientos registrados." pagination-label="Paginación de movimientos del insumo" />
    </x-slot:movimientos>
</x-inventory-detail-dialog>
