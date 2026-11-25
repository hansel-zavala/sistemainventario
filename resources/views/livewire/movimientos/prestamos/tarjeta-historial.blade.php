@php
    $id = 'prestamo-'.$prestamo->id;
    $titulo = 'Préstamo #'.$prestamo->id;
    $fechaPrestamo = $prestamo->fecha_prestamo;
    $fechaPrevista = $prestamo->fecha_prevista_devolucion;
    [$estado, $estiloEstado] = match (true) {
        $prestamo->estado === 'completado' => ['Completado', 'bg-green-100 text-green-800'],
        $prestamo->esta_vencido => ['Vencido', 'bg-red-100 text-red-800'],
        default => ['Activo', 'bg-blue-100 text-blue-800'],
    };
    $datosEntrega = [
        'Destino' => $prestamo->departamentoDestino?->nombre ?? 'No disponible',
        'Recibe' => $prestamo->persona_recibe,
        'Registrado por' => $prestamo->usuario?->name ?? 'No disponible',
    ];
    $formatearCantidad = fn (float $cantidad): string => rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.');
    $articulos = $prestamo->detalles->map(function (\App\Models\PrestamoDetalle $detalle) use ($formatearCantidad): array {
        $activo = \App\Services\PrestamosTable::asset($detalle);
        $pendiente = $detalle->cantidad_pendiente;

        return [
            'id' => $detalle->id,
            'name' => $activo['articulo'],
            'subtitle' => implode(' · ', array_filter([$activo['tipo'], $activo['codigo']])),
            'loaned' => $formatearCantidad((float) $detalle->cantidad_prestada),
            'returned' => $formatearCantidad((float) $detalle->cantidad_devuelta),
            'pending' => $formatearCantidad($pendiente),
            'pendingQuantity' => $pendiente,
            'cellClasses' => ['pending' => $pendiente > 0 ? 'text-red-700' : 'text-green-700'],
        ];
    });
    $cantidadArticulos = $articulos->count();
    $cantidadPendiente = round($articulos->sum('pendingQuantity'), 2);
    $columnasDetalle = [
        'loaned' => ['Prestado', 'text-ink'],
        'returned' => ['Devuelto', 'text-ink'],
        'pending' => ['Pendiente', 'font-semibold'],
    ];
@endphp

<article wire:key="{{ $id }}" aria-labelledby="{{ $id }}-title" x-data="{ expanded: false }"
    class="rounded-xl border border-line bg-canvas/45 text-ink">
    <header class="rounded-t-xl border-b border-line bg-line/30 px-3 py-4 sm:px-4">
        <div class="flex flex-wrap items-start justify-between gap-x-5 gap-y-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="{{ $id }}-title" class="text-sm font-semibold">{{ $titulo }}</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $estiloEstado }}">{{ $estado }}</span>
                </div>
                <time datetime="{{ $fechaPrestamo->toIso8601String() }}"
                    class="mt-1.5 flex items-center gap-1 text-xs text-muted tabular-nums">
                    <x-icon name="calendar" class="size-3.5" />
                    {{ $fechaPrestamo->format('d/m/Y h:i A') }}
                </time>
            </div>
            <div class="ml-auto min-w-0 text-right">
                <p class="text-xs text-muted">Devolución prevista</p>
                <time datetime="{{ $fechaPrevista->toIso8601String() }}" class="mt-0.5 block text-xs font-medium tabular-nums">
                    {{ $fechaPrevista->format('d/m/Y h:i A') }}
                </time>
                @if ($cantidadArticulos)
                    <x-inventory-history-detail-toggle :controls="$id.'-detail'" :title="$titulo"
                        open-label="Ver artículos" close-label="Ocultar detalle" context-prefix="del" class="mt-2" />
                @endif
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
            @foreach ($datosEntrega as $etiqueta => $valor)
                <div @class(['min-w-0', 'col-span-2 sm:col-span-1' => $etiqueta === 'Registrado por'])>
                    <dt class="text-xs text-muted uppercase">{{ $etiqueta }}</dt>
                    <dd @class(['mt-1 text-sm wrap-anywhere', 'font-medium' => $etiqueta === 'Destino'])>
                        {{ filled($valor) ? $valor : 'Sin especificar' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </header>

    <div class="px-3 py-4 sm:px-4">
        @if ($cantidadArticulos)
            <p class="text-sm font-semibold wrap-anywhere">
                {{ $cantidadArticulos === 1 ? $articulos->first()['name'] : $cantidadArticulos.' artículos prestados' }}
            </p>
            <p class="mt-1 text-xs text-muted">
                @if ($cantidadPendiente > 0)
                    {{ $formatearCantidad($cantidadPendiente) }} {{ $cantidadPendiente === 1.0 ? 'unidad pendiente' : 'unidades pendientes' }} de devolución
                @else
                    Todos los artículos han sido devueltos
                @endif
            </p>
        @else
            <p class="text-sm text-muted">Este préstamo no tiene artículos registrados.</p>
        @endif

        <div id="{{ $id }}-detail" @if ($cantidadArticulos) x-show="expanded" style="display: none" @endif>
            @if ($cantidadArticulos)
                <x-inventory-history-detail-table :id="$id" :title="$titulo" :details="$articulos"
                    item-label="Activo / Código" :columns="$columnasDetalle" context-prefix="del"
                    class="mt-4 [&_thead]:text-[10px] [&_thead]:uppercase [&_thead_th]:wrap-anywhere" />

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                    <p>{{ $cantidadArticulos }} {{ $cantidadArticulos === 1 ? 'artículo' : 'artículos' }} en el préstamo</p>
                    @if ($cantidadPendiente > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">Devolución pendiente</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800">
                            <x-icon name="check" class="size-3" />
                            Sin pendientes
                        </span>
                    @endif
                </div>
            @endif
            @if (filled($prestamo->observaciones))
                <p class="mt-3 border-t border-line pt-3 text-xs whitespace-pre-line text-muted wrap-anywhere">
                    <span class="font-medium text-ink">Observaciones:</span> {{ $prestamo->observaciones }}
                </p>
            @endif
        </div>
    </div>
</article>
