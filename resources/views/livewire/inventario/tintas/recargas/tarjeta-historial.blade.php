@php
    $cantidadTintas = $recarga->detalles->count();
    $variasTintas = $cantidadTintas > 1;
    $etiquetaTintas = $cantidadTintas.($cantidadTintas === 1 ? ' tinta' : ' tintas');
    $cantidadVistaPrevia = $cantidadTintas > 3 ? 2 : $cantidadTintas;
    $tintasRestantes = $cantidadTintas - $cantidadVistaPrevia;
    $fecha = $recarga->created_at;
    $tecnico = $recarga->usuario?->name ?? 'Usuario no disponible';
    $impresora = $recarga->equipo
        ? trim(($recarga->equipo->marca?->nombre ?? '').' '.$recarga->equipo->modelo)
        : 'Impresora no disponible';
    $formatearPorcentaje = fn (string $porcentaje): string => rtrim(rtrim($porcentaje, '0'), '.').' %';
    $niveles = [
        'porcentaje_antes' => ['Anterior', 'text-ink'],
        'porcentaje_agregado' => ['Agregado', 'font-medium text-green-700'],
        'porcentaje_despues' => ['Posterior', 'font-semibold text-ink'],
    ];
@endphp

<article wire:key="recarga-{{ $recarga->id }}" aria-labelledby="recarga-titulo-{{ $recarga->id }}"
    x-data="{ expanded: false }" class="rounded-xl border border-line bg-canvas/45 px-3 py-4 text-ink sm:px-4">
    <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="recarga-titulo-{{ $recarga->id }}" class="text-sm font-semibold">Recarga #{{ $recarga->id }}</h2>
                @if ($cantidadTintas)
                    <span class="rounded-full bg-line px-2 py-0.5 text-xs font-medium">{{ $etiquetaTintas }}</span>
                @endif
            </div>
            <time datetime="{{ $fecha->toIso8601String() }}" class="mt-1 block text-xs text-muted tabular-nums">
                {{ $fecha->format('d/m/Y · H:i') }}
            </time>
        </div>
        <p class="flex min-w-0 items-start gap-1.5 text-xs text-muted">
            <x-icon name="user" class="mt-0.5 size-3.5" />
            <span class="wrap-anywhere"><span class="sr-only">Técnico </span>{{ $tecnico }}</span>
        </p>
        <p class="w-full text-xs text-muted wrap-anywhere">
            <span class="font-medium text-ink">{{ $impresora }}</span>
            @if ($recarga->equipo?->numero_inventario)
                <span class="mx-1" aria-hidden="true">·</span> Inventario {{ $recarga->equipo->numero_inventario }}
            @endif
        </p>
    </header>

    @if ($cantidadTintas === 1)
        @php($detalle = $recarga->detalles->first())

        <div class="mt-4 flex items-end gap-2 sm:items-center">
            <dl class="grid min-w-0 flex-1 grid-cols-3 gap-x-2 gap-y-2.5 sm:grid-cols-[minmax(0,1fr)_repeat(3,5rem)]">
                <div class="col-span-3 min-w-0 sm:col-span-1">
                    <dt class="text-xs text-muted">Tinta</dt>
                    <dd class="mt-0.5 font-medium text-sm wrap-anywhere">
                        {{ $detalle->tinta?->nombre ?? 'Tinta no disponible' }}
                        @if ($detalle->tinta?->color)
                            <span class="font-normal text-muted">— {{ $detalle->tinta->color }}</span>
                        @endif
                        @if ($detalle->tinta?->marca)
                            <span class="block text-xs font-normal text-muted">{{ $detalle->tinta->marca->nombre }}</span>
                        @endif
                    </dd>
                </div>
                @foreach ($niveles as $campo => [$etiqueta, $estilo])
                    <div class="text-center">
                        <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                        <dd class="mt-0.5 text-sm whitespace-nowrap tabular-nums {{ $estilo }}">
                            {{ $campo === 'porcentaje_agregado' ? '+ ' : '' }}{{ $formatearPorcentaje($detalle->$campo) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
            <button type="button" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded"
                aria-controls="recarga-detalle-{{ $recarga->id }}"
                :aria-label="expanded ? 'Ocultar detalle de la recarga #{{ $recarga->id }}' : 'Ver detalle de la recarga #{{ $recarga->id }}'"
                aria-label="Ver detalle de la recarga #{{ $recarga->id }}"
                class="group inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted hover:bg-line hover:text-ink">
                <x-icon name="chevron" class="size-4 rotate-90 group-aria-expanded:-rotate-90" />
            </button>
        </div>
    @elseif ($variasTintas)
        <div x-show="!expanded" class="mt-4 border-t border-line pt-3">
            <ul aria-label="Vista previa de tintas" class="space-y-2">
                @foreach ($recarga->detalles->take($cantidadVistaPrevia) as $detalle)
                    <li wire:key="recarga-resumen-{{ $detalle->id }}" class="flex justify-between gap-3 text-sm">
                        <span class="min-w-0 wrap-anywhere">
                            {{ $detalle->tinta?->nombre ?? 'Tinta no disponible' }}
                            @if ($detalle->tinta?->color)
                                <span class="text-muted">— {{ $detalle->tinta->color }}</span>
                            @endif
                        </span>
                        <span class="shrink-0 font-medium text-green-700 tabular-nums">
                            <span class="sr-only">Agregado </span>+ {{ $formatearPorcentaje($detalle->porcentaje_agregado) }}
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($tintasRestantes > 0)
                <p class="mt-2 text-xs text-muted">Y {{ $tintasRestantes }} tintas más…</p>
            @endif
        </div>
    @else
        <p class="mt-4 text-sm text-muted">Esta recarga no tiene tintas registradas.</p>
    @endif

    <div id="recarga-detalle-{{ $recarga->id }}" @if ($cantidadTintas) x-show="expanded" style="display: none" @endif>
        @if ($variasTintas)
            <h3 class="mt-4 text-sm font-semibold">Tintas registradas</h3>
            <table class="mt-3 w-full table-fixed border-t border-line text-sm">
                <caption class="sr-only">Niveles de tinta en la recarga #{{ $recarga->id }}</caption>
                <thead class="border-b border-line text-xs text-muted">
                    <tr>
                        <th scope="col" class="py-2 pr-2 text-left font-medium">Tinta</th>
                        @foreach ($niveles as [$etiqueta])
                            <th scope="col" class="w-1/5 py-2 text-right font-medium sm:w-24">{{ $etiqueta }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($recarga->detalles as $detalle)
                        <tr wire:key="recarga-detalle-fila-{{ $detalle->id }}">
                            <th scope="row" class="py-2 pr-2 text-left font-medium wrap-anywhere">
                                {{ $detalle->tinta?->nombre ?? 'Tinta no disponible' }}
                                @if ($detalle->tinta?->color || $detalle->tinta?->marca)
                                    <span class="block text-xs font-normal text-muted">
                                        {{ implode(' · ', array_filter([$detalle->tinta?->color, $detalle->tinta?->marca?->nombre])) }}
                                    </span>
                                @endif
                            </th>
                            @foreach ($niveles as $campo => [, $estilo])
                                <td class="py-2 text-right text-xs whitespace-nowrap tabular-nums sm:text-sm {{ $estilo }}">
                                    {{ $campo === 'porcentaje_agregado' ? '+ ' : '' }}{{ $formatearPorcentaje($detalle->$campo) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($cantidadTintas || $recarga->observaciones)
            <footer class="mt-3 space-y-2 border-t border-line pt-3 text-xs text-muted">
                @if ($variasTintas)
                    <p class="flex items-center gap-1.5">
                        <x-icon name="check" class="size-4 text-green-700" />
                        {{ $etiquetaTintas }} registradas
                    </p>
                @elseif ($cantidadTintas === 1)
                    <p>Registrado por {{ $tecnico }} el {{ $fecha->format('d/m/Y') }} a las {{ $fecha->format('H:i') }}.</p>
                @endif
                @if ($recarga->observaciones)
                    <p class="whitespace-pre-line wrap-anywhere"><span class="font-medium text-ink">Observaciones:</span> {{ $recarga->observaciones }}</p>
                @endif
            </footer>
        @endif
    </div>

    @if ($variasTintas)
        <button type="button" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded"
            aria-controls="recarga-detalle-{{ $recarga->id }}"
            class="group mt-3 inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-full border border-line bg-surface px-3 text-sm font-medium text-muted hover:bg-accent-soft hover:text-accent">
            <span class="group-aria-expanded:hidden">Ver registro completo</span>
            <span class="hidden group-aria-expanded:inline">Ocultar detalle</span>
            <x-icon name="chevron" class="size-4 group-aria-expanded:-rotate-90" />
        </button>
    @endif
</article>
