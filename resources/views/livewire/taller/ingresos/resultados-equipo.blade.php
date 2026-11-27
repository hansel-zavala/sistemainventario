@if (filled(trim($consulta)))
    @if ($resultados->isEmpty())
        <p role="status" class="mt-2 text-sm text-muted">No se encontraron equipos con ese número de inventario.</p>
    @else
        <ul aria-label="Equipos encontrados" class="mt-2 max-w-lg divide-y divide-line rounded-lg border border-line bg-surface">
            @foreach ($resultados as $resultado)
                <li wire:key="{{ $accion }}-{{ $resultado->id }}">
                    <button type="button" wire:click="{{ $accion }}({{ $resultado->id }})" wire:loading.attr="disabled"
                        class="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-accent-soft">
                        <span><strong>{{ $resultado->numero_inventario }}</strong> <span class="text-muted">{{ $resultado->tipoEquipo->nombre }} {{ $resultado->marca->nombre }} {{ $resultado->modelo }}</span></span>
                        <span class="shrink-0 font-medium text-accent">Seleccionar</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
@endif
