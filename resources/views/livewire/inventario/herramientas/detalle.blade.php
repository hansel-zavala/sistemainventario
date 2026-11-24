@php
    $porCantidad = $herramienta->tipo_control === 'cantidad';
    $disponible = $herramienta->activo ? \App\Services\HerramientasTable::available($herramienta) : 0;
    $formatearCantidad = fn ($cantidad) => rtrim(rtrim(number_format((float) $cantidad, 2, '.', ''), '0'), '.');
    $formatearExistencia = fn ($cantidad) => $formatearCantidad($cantidad).' '.((float) $cantidad === 1.0
        ? $herramienta->unidadMedida->nombre_singular : $herramienta->unidadMedida->nombre_plural);
    [$estado, $tono] = match (true) {
        ! $herramienta->activo => ['Inactiva', 'neutral'],
        ! $porCantidad && $herramienta->esta_prestada => ['Prestada', 'warning'],
        $porCantidad && $disponible <= 0 => ['Sin disponibilidad', 'danger'],
        $porCantidad => [$formatearCantidad($disponible).' disponibles', 'positive'],
        default => ['Disponible', 'positive'],
    };
    $unidad = $herramienta->unidadMedida;
    $identificacion = [
        'Nombre' => $herramienta->nombre,
        'Código interno' => $herramienta->codigo_interno,
        'Categoría' => $herramienta->categoria->nombre,
        'Marca' => $herramienta->marca?->nombre,
        'Unidad de medida' => $unidad->nombre_singular.($unidad->abreviatura ? ' ('.$unidad->abreviatura.')' : ''),
        'Tipo de control' => $porCantidad ? 'Por cantidad' : 'Individual',
        'Estado' => $herramienta->activo ? 'Activa' : 'Inactiva',
    ];
    $existencias = ['Existencia actual' => $formatearExistencia($herramienta->cantidad_actual)];
    if ($porCantidad) {
        $existencias += [
            'Stock mínimo' => $formatearExistencia($herramienta->stock_minimo),
            'Cantidad prestada' => $formatearExistencia($herramienta->cantidad_prestada_pendiente),
            'Cantidad disponible' => $formatearExistencia($disponible),
        ];
    }
    $registro = [
        'Registrada por' => $herramienta->creador?->name,
        'Fecha de registro' => $herramienta->created_at?->format('d/m/Y H:i'),
        'Última actualización' => $herramienta->updated_at?->format('d/m/Y H:i'),
    ];
@endphp

<x-inventory-detail-dialog id="herramienta-detalle" wire:key="detalle-herramienta-{{ $herramienta->id }}"
    trigger="ver-herramienta-{{ $herramienta->id }}" label="Ficha de la herramienta" icon="tools" :show-technical-history="false"
    :title="$herramienta->nombre" :description="filled($herramienta->codigo_interno) ? 'Código '.$herramienta->codigo_interno : $herramienta->categoria->nombre"
    :badge="$estado" :badge-tone="$tono" edit-action="editarHerramienta({{ $herramienta->id }})" edit-label="Editar herramienta">
    <x-slot:general>
        @foreach (['Identificación de la herramienta' => $identificacion, 'Existencias y observaciones' => $existencias, 'Registro' => $registro] as $seccion => $campos)
            <section class="py-4 sm:py-5">
                <h3 class="mb-3 text-sm font-semibold">{{ $seccion }}</h3>
                <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                    @foreach ($campos as $etiqueta => $valor)
                        <div class="min-w-0">
                            <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                            <dd class="mt-0.5 text-sm font-medium wrap-anywhere">{{ filled($valor) ? $valor : 'Sin registrar' }}</dd>
                        </div>
                    @endforeach
                    @if ($seccion === 'Existencias y observaciones')
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-muted">Observaciones</dt>
                            <dd @class(['mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap', 'text-muted italic' => blank($herramienta->observaciones)])>{{ filled($herramienta->observaciones) ? $herramienta->observaciones : 'Sin observaciones registradas.' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endforeach
    </x-slot:general>

    <x-slot:movimientos>
        <x-inventory-stock-movements :movimientos="$movimientos" id="herramienta-movimiento"
            empty-message="Esta herramienta aún no tiene movimientos registrados." pagination-label="Paginación de movimientos de la herramienta" />
    </x-slot:movimientos>
</x-inventory-detail-dialog>
