@if ($historial->isEmpty())
    <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">{{ $mensajeVacio }}</p>
@else
    <ol class="divide-y divide-line rounded-xl border border-line">
        @foreach ($historial as $visita)
            <li wire:key="equipo-taller-ingreso-{{ $visita->id }}" class="px-4 py-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <a href="{{ route('taller.ingresos.ver', $visita) }}" class="font-semibold text-accent hover:underline">{{ $visita->folio }} <span class="sr-only">Ver ingreso</span></a>
                    <time datetime="{{ $visita->fecha_ingreso->toIso8601String() }}" class="text-xs text-muted">{{ $visita->fecha_ingreso->format('d/m/Y H:i') }}</time>
                </div>
                <p class="mt-1 text-sm wrap-anywhere text-ink">{{ \Illuminate\Support\Str::limit($visita->motivo, 150) }}</p>
                <p class="mt-1 text-xs text-muted">{{ $visita->estado() }} · {{ $visita->tecnico_nombre }}</p>
            </li>
        @endforeach
    </ol>
@endif
@if ($historial->hasPages())
    <div class="mt-4"><x-table-pagination :paginator="$historial" label="Paginación del historial técnico" /></div>
@endif
