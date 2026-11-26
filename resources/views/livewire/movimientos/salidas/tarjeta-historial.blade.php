@php
    $id = 'salidas-movimiento-'.$movimiento->id;
    $titulo = 'Salida #'.$movimiento->id;
    $fecha = $movimiento->fecha_movimiento;
    $tipos = \App\Services\MovimientosTable::TYPES;
    $formatearCantidad = fn (string $cantidad): string => rtrim(rtrim($cantidad, '0'), '.');
    $articulos = $movimiento->detalles->map(fn (\App\Models\MovimientoInventarioDetalle $detalle): array => [
        'id' => $detalle->id,
        'name' => \App\Services\MovimientosTable::articleName($detalle),
        'subtitle' => $tipos[$detalle->tipo_item] ?? $detalle->tipo_item,
        'before' => $formatearCantidad($detalle->existencia_anterior),
        'withdrawn' => '− '.$formatearCantidad($detalle->cantidad),
        'after' => $formatearCantidad($detalle->existencia_posterior),
    ]);
    $cantidadArticulos = $articulos->count();
    $vistaPrevia = $articulos->take(2);
    $articulosRestantes = $cantidadArticulos - $vistaPrevia->count();
    $mostrarTablaCompleta = $articulosRestantes > 0;
    $tieneObservaciones = filled($movimiento->observaciones);
    $mostrarDetalle = $mostrarTablaCompleta || ($cantidadArticulos > 0 && $tieneObservaciones);
    $columnasDetalle = [
        'before' => ['Anterior', 'text-ink'],
        'withdrawn' => ['Salida', 'font-medium text-red-700'],
        'after' => ['Posterior', 'font-semibold text-ink'],
    ];
    $datosEntrega = [
        'Destino' => \App\Services\SalidasTable::destinationName($movimiento->departamentoDestino),
        'Recibe' => $movimiento->persona_recibe,
        'Motivo' => $movimiento->motivo,
    ];
@endphp

<article wire:key="{{ $id }}" aria-labelledby="{{ $id }}-title" x-data="{ expanded: false }"
    class="rounded-xl border border-line bg-canvas/45 px-3 py-4 text-ink sm:px-4">
    <header class="-mx-3 -mt-4 flex flex-wrap items-start justify-between gap-x-5 gap-y-3 rounded-t-xl bg-line/30 px-3 py-4 sm:-mx-4 sm:px-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="{{ $id }}-title" class="text-sm font-semibold">{{ $titulo }}</h2>
                <span class="rounded-full bg-line px-2 py-0.5 text-xs font-medium">
                    {{ $cantidadArticulos }} {{ $cantidadArticulos === 1 ? 'artículo' : 'artículos' }}
                </span>
            </div>
            <time datetime="{{ $fecha->toIso8601String() }}"
                class="mt-1.5 flex items-center gap-1 text-xs text-muted tabular-nums">
                <x-icon name="calendar" class="size-3.5" />
                {{ $fecha->format('d/m/Y · H:i') }}
            </time>
        </div>
        <div class="ml-auto min-w-0 text-right">
            <p class="text-xs text-muted">Registrado por</p>
            <p class="text-xs font-medium wrap-anywhere">{{ $movimiento->usuario?->name ?? 'Usuario no disponible' }}</p>
            @if ($mostrarDetalle)
                <x-inventory-history-detail-toggle :controls="$id.'-detail'" :title="$titulo" class="mt-2" />
            @endif
        </div>
    </header>

    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
        @foreach ($datosEntrega as $etiqueta => $valor)
            <div @class(['min-w-0', 'col-span-2 sm:col-span-1' => $etiqueta === 'Motivo'])>
                <dt class="text-xs text-muted uppercase">{{ $etiqueta }}</dt>
                <dd @class(['mt-1 text-sm wrap-anywhere', 'font-medium' => $etiqueta === 'Destino'])>
                    {{ filled($valor) ? $valor : 'Sin especificar' }}
                </dd>
            </div>
        @endforeach
    </dl>

    @if ($cantidadArticulos)
        <div @if ($mostrarTablaCompleta) x-show="!expanded" @endif class="mt-5">
            <x-inventory-history-detail-table :id="$id.'-preview'" :title="$titulo" :details="$vistaPrevia"
                item-label="Artículo" :columns="$columnasDetalle" class="[&_thead]:uppercase" />
            @if ($mostrarTablaCompleta)
                <p class="mt-2 text-xs text-muted">
                    {{ $articulosRestantes === 1 ? 'Y 1 artículo más…' : 'Y '.$articulosRestantes.' artículos más…' }}
                </p>
            @endif
        </div>
    @else
        <p class="mt-5 border-t border-line pt-4 text-sm text-muted">Esta salida no tiene artículos registrados.</p>
    @endif

    <div id="{{ $id }}-detail" @if ($mostrarDetalle) x-show="expanded" style="display: none" @endif>
        @if ($mostrarTablaCompleta)
            <h3 class="mt-5 border-t border-line pt-4 text-sm font-semibold">Artículos registrados</h3>
            <x-inventory-history-detail-table :id="$id" :title="$titulo" :details="$articulos" item-label="Artículo"
                :columns="$columnasDetalle" class="mt-3 [&_thead]:uppercase" />
        @endif
        @if ($tieneObservaciones)
            <p class="mt-3 border-t border-line pt-3 text-xs whitespace-pre-line text-muted wrap-anywhere">
                <span class="font-medium text-ink">Observaciones:</span> {{ $movimiento->observaciones }}
            </p>
        @endif
    </div>
</article>
