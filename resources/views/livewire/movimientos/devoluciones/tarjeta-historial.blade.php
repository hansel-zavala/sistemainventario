@php
    $id = 'devolucion-'.$prestamo->id;
    $titulo = 'Préstamo #'.$prestamo->id;
    $fechaPrevista = $prestamo->fecha_prevista_devolucion;
    [$estado, $estiloEstado] = match (true) {
        $prestamo->estado === 'completado' => ['Completado', 'bg-green-100 text-green-800'],
        $prestamo->esta_vencido => ['Vencido', 'bg-red-100 text-red-800'],
        default => ['Activo', 'bg-blue-100 text-blue-800'],
    };
    $puedeRegistrar = $prestamo->estado === 'activo' && $prestamo->detalles->contains('estado', 'prestado');
    $formatearCantidad = fn (float $cantidad): string => rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.');
    $activos = $prestamo->detalles->map(function (\App\Models\PrestamoDetalle $detalle) use ($formatearCantidad): array {
        $activo = \App\Services\PrestamosTable::asset($detalle);
        $pendiente = $detalle->cantidad_pendiente;

        return [
            'id' => $detalle->id,
            'name' => $activo['articulo'],
            'subtitle' => $activo['codigo'] !== '' ? $activo['codigo'] : 'Control por cantidad',
            'loaned' => $formatearCantidad((float) $detalle->cantidad_prestada),
            'returned' => $formatearCantidad((float) $detalle->cantidad_devuelta),
            'pending' => $formatearCantidad($pendiente),
            'pendingQuantity' => $pendiente,
            'cellClasses' => ['pending' => $pendiente > 0 ? 'text-ink' : 'text-green-700'],
            'returns' => $detalle->devoluciones,
        ];
    });
    $entregas = $activos->flatMap(fn (array $activo) => $activo['returns']->map(
        fn (\App\Models\PrestamoDevolucion $entrega): array => ['asset' => $activo['name'], 'record' => $entrega],
    ))->sortBy([['record.fecha_devolucion', 'asc'], ['record.id', 'asc']]);
    $cantidadActivos = $activos->count();
    $cantidadPendiente = round($activos->sum('pendingQuantity'), 2);
    $cantidadPrestada = round($prestamo->detalles->sum('cantidad_prestada'), 2);
    $columnasDetalle = [
        'loaned' => ['Prestado', 'text-ink'],
        'returned' => ['Devuelto', 'font-medium text-green-700'],
        'pending' => ['Pendiente', 'font-semibold'],
    ];
@endphp

<article wire:key="{{ $id }}" aria-labelledby="{{ $id }}-title" x-data="{ expanded: false }"
    class="rounded-xl border border-line bg-canvas/45 text-ink">
    <header class="flex flex-wrap items-start justify-between gap-x-5 gap-y-3 rounded-t-xl border-b border-line bg-line/30 px-3 py-4 sm:px-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="{{ $id }}-title" class="text-sm font-semibold">{{ $titulo }}</h2>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $estiloEstado }}">{{ $estado }}</span>
            </div>
            <p class="mt-2 flex items-start gap-1.5 text-xs font-medium">
                <x-icon name="building" class="size-3.5 text-muted" />
                <span class="wrap-anywhere"><span class="sr-only">Departamento </span>{{ $prestamo->departamentoDestino?->nombre ?? 'No disponible' }}</span>
            </p>
            <p class="mt-2 flex items-start gap-1.5 text-xs text-muted">
                <x-icon name="user" class="size-3.5" />
                <span class="wrap-anywhere"><span class="sr-only">Responsable </span>{{ $prestamo->persona_recibe }}</span>
            </p>
        </div>
        <div class="ml-auto min-w-0 text-right">
            <p class="text-xs text-muted">Fecha prevista</p>
            <time datetime="{{ $fechaPrevista->toIso8601String() }}" class="mt-0.5 block text-xs font-medium tabular-nums">
                {{ $fechaPrevista->format('d/m/Y h:i A') }}
            </time>
            <div class="mt-2 flex flex-wrap justify-end gap-2">
                @if ($puedeRegistrar)
                    <button type="button" wire:click="abrirDevolucion({{ $prestamo->id }})"
                        class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-1.5 rounded-full bg-ink px-3 text-sm font-medium text-white hover:brightness-90">
                        <x-icon name="plus" class="size-4" />
                        Registrar devolución
                    </button>
                @endif
                <x-inventory-history-detail-toggle :controls="$id.'-detail'" :title="$titulo"
                    open-label="Ver artículos e historial" close-label="Ocultar detalle" context-prefix="del" />
            </div>
        </div>
    </header>

    <div class="px-3 py-4 sm:px-4">
        <p class="text-sm font-semibold">{{ $cantidadActivos }} {{ $cantidadActivos === 1 ? 'activo' : 'activos' }} en el préstamo</p>
        <p class="mt-1 text-xs text-muted">
            @if ($cantidadActivos === 0)
                Este préstamo no tiene activos registrados.
            @elseif ($cantidadPendiente > 0)
                {{ $formatearCantidad($cantidadPendiente) }} de {{ $formatearCantidad($cantidadPrestada) }}
                {{ $cantidadPrestada === 1.0 ? 'unidad pendiente' : 'unidades pendientes' }}
            @else
                Todos los artículos fueron devueltos
            @endif
        </p>

        <div id="{{ $id }}-detail" x-show="expanded" style="display: none">
            @if ($cantidadActivos)
                <x-inventory-history-detail-table :id="$id" :title="$titulo" :details="$activos"
                    item-label="Activo" :columns="$columnasDetalle" context-prefix="del"
                    class="mt-4 [&_thead]:text-[10px] [&_thead]:uppercase [&_thead_th]:wrap-anywhere" />
            @endif

            <section aria-labelledby="{{ $id }}-history-title" class="mt-4 border-t border-line pt-3">
                <div class="flex items-center gap-2">
                    <x-icon name="history" class="size-4 text-muted" />
                    <h3 id="{{ $id }}-history-title" class="text-sm font-semibold">Historial de devoluciones</h3>
                    <span class="rounded-full bg-line px-2 py-0.5 text-xs font-medium tabular-nums">{{ $entregas->count() }}</span>
                </div>
                @if ($entregas->isEmpty())
                    <p class="mt-2 text-xs text-muted">Todavía no se han registrado devoluciones.</p>
                @else
                    <ol class="mt-3 space-y-3">
                        @foreach ($entregas as ['asset' => $activo, 'record' => $entrega])
                            @php($cantidadEntregada = (float) $entrega->cantidad)
                            <li wire:key="devoluciones-entrega-{{ $entrega->id }}" class="flex items-start gap-2">
                                <x-icon name="check" class="mt-0.5 size-4 text-green-700" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium wrap-anywhere">
                                        {{ $activo }} · {{ $formatearCantidad($cantidadEntregada) }} {{ $cantidadEntregada === 1.0 ? 'unidad' : 'unidades' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted wrap-anywhere">
                                        <time datetime="{{ $entrega->fecha_devolucion->toIso8601String() }}">{{ $entrega->fecha_devolucion->format('d/m/Y h:i A') }}</time>
                                        · Por {{ $entrega->usuario?->name ?? 'Usuario no disponible' }}
                                    </p>
                                    @if (filled($entrega->observaciones))
                                        <p class="mt-1 text-xs whitespace-pre-line text-muted wrap-anywhere">{{ $entrega->observaciones }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</article>
