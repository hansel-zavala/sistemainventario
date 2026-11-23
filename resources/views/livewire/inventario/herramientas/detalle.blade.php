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
    $acciones = \App\Services\HistorialMovimientosTable::TYPES + [
        'actualizacion' => 'Actualización de datos',
        'desactivacion' => 'Desactivación',
        'activacion' => 'Reactivación',
    ];
@endphp

<x-inventory-detail-dialog id="herramienta-detalle" wire:key="detalle-herramienta-{{ $herramienta->id }}"
    trigger="ver-herramienta-{{ $herramienta->id }}" label="Ficha de la herramienta" icon="tools"
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
        @if ($movimientos->isEmpty())
            <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">Esta herramienta aún no tiene movimientos registrados.</p>
        @else
            <ol class="divide-y divide-line rounded-xl border border-line">
                @foreach ($movimientos as $movimiento)
                    @php($fecha = \Illuminate\Support\Carbon::parse($movimiento->fecha))
                    <li wire:key="herramienta-movimiento-{{ $movimiento->fuente }}-{{ $movimiento->origen_id }}" class="space-y-2 px-4 py-3">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                            <div>
                                <p class="text-sm font-semibold">{{ $acciones[$movimiento->tipo] }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $movimiento->responsable ?? 'Usuario no disponible' }}</p>
                            </div>
                            <time datetime="{{ $fecha->toIso8601String() }}" class="text-xs text-muted">{{ $fecha->format('d/m/Y H:i') }}</time>
                        </div>
                        @if ($movimiento->cantidad !== null)
                            <dl class="flex flex-wrap gap-x-5 gap-y-1 text-xs">
                                @foreach (['Cantidad' => $movimiento->cantidad, 'Existencia anterior' => $movimiento->anterior, 'Existencia posterior' => $movimiento->posterior] as $etiqueta => $cantidad)
                                    @if ($cantidad !== null)
                                        <div class="flex gap-1">
                                            <dt class="text-muted">{{ $etiqueta }}</dt>
                                            <dd class="font-medium">{{ $formatearCantidad($cantidad) }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        @endif
                        @if (filled($movimiento->detalle))
                            <p class="text-xs wrap-anywhere whitespace-pre-wrap text-muted">{{ $movimiento->detalle }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
        @if ($movimientos->hasPages())
            <div class="mt-4">
                <x-table-pagination :paginator="$movimientos" label="Paginación de movimientos de la herramienta" />
            </div>
        @endif
    </x-slot:movimientos>
</x-inventory-detail-dialog>
