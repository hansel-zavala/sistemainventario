@props(['movimientos', 'id', 'emptyMessage', 'paginationLabel', 'showUnit' => false])

@php
    $formatearCantidad = fn ($cantidad) => rtrim(rtrim(number_format((float) $cantidad, 2, '.', ''), '0'), '.');
    $acciones = \App\Services\HistorialMovimientosTable::TYPES + [
        'actualizacion' => 'Actualización de datos',
        'desactivacion' => 'Desactivación',
        'activacion' => 'Reactivación',
    ];
@endphp

@if ($movimientos->isEmpty())
    <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">{{ $emptyMessage }}</p>
@else
    <ol class="divide-y divide-line rounded-xl border border-line">
        @foreach ($movimientos as $movimiento)
            @php($fecha = $movimiento->fecha === null ? null : \Illuminate\Support\Carbon::parse($movimiento->fecha))
            <li wire:key="{{ $id }}-{{ $movimiento->fuente }}-{{ $movimiento->origen_id }}" class="space-y-2 px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                    <div>
                        <p class="text-sm font-semibold">{{ $acciones[$movimiento->tipo] }}</p>
                        <p class="mt-0.5 text-xs text-muted">{{ $movimiento->responsable ?? 'Usuario no disponible' }}</p>
                    </div>
                    @if ($fecha)
                        <time datetime="{{ $fecha->toIso8601String() }}" class="text-xs text-muted">{{ $fecha->format('d/m/Y H:i') }}</time>
                    @else
                        <span class="text-xs text-muted">Fecha no disponible</span>
                    @endif
                </div>
                @if ($movimiento->cantidad !== null)
                    <dl class="flex flex-wrap gap-x-5 gap-y-1 text-xs">
                        @foreach (['Cantidad' => $movimiento->cantidad, 'Existencia anterior' => $movimiento->anterior, 'Existencia posterior' => $movimiento->posterior] as $etiqueta => $cantidad)
                            @if ($cantidad !== null)
                                <div class="flex gap-1">
                                    <dt class="text-muted">{{ $etiqueta }}</dt>
                                    <dd class="font-medium">{{ $formatearCantidad($cantidad) }}{{ $showUnit ? ' '.$movimiento->unidad : '' }}</dd>
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
        <x-table-pagination :paginator="$movimientos" :label="$paginationLabel" />
    </div>
@endif
