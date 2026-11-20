@php
    $pestanas = [
        'general' => 'Información general',
        'tecnico' => 'Historial técnico',
        'movimientos' => 'Movimientos',
    ];
    $estado = \App\Services\EquiposTable::status($equipo);
    $departamento = $equipo->departamento;
    $identificacion = [
        'Número de inventario' => $equipo->numero_inventario,
        'Número de serie' => $equipo->numero_serie,
        'Categoría' => $equipo->categoria->nombre,
        'Tipo de equipo' => $equipo->tipoEquipo->nombre,
        'Departamento de procedencia' => $departamento->nombre.($departamento->sigla ? ' ('.$departamento->sigla.')' : ''),
        'Marca' => $equipo->marca->nombre,
    ];
    $caracteristicas = ['Modelo' => $equipo->modelo, 'Color' => $equipo->color];
    $registro = [
        'Registrado por' => $equipo->creador?->name,
        'Fecha de registro' => $equipo->created_at?->format('d/m/Y H:i'),
        'Última actualización' => $equipo->updated_at?->format('d/m/Y H:i'),
    ];
    $acciones = [
        'actualizacion' => 'Actualización de datos',
        'desactivacion' => 'Desactivación',
        'activacion' => 'Reactivación',
        'prestamo' => 'Préstamo',
        'devolucion' => 'Devolución',
    ];
@endphp

<dialog id="equipo-detalle" wire:key="detalle-equipo-{{ $equipo->id }}" wire:ignore.self
    data-trigger="ver-equipo-{{ $equipo->id }}"
    x-data="{
        tab: 'general',
        moveTab(event, direction) {
            const tabs = [...event.currentTarget.querySelectorAll('[role=tab]')];
            const index = tabs.indexOf(event.target);
            tabs[(index + direction + tabs.length) % tabs.length].focus();
        },
        closeDetails() {
            const dialog = this.$root;
            dialog.close();
            document.getElementById(dialog.dataset.trigger)?.focus({ preventScroll: true });
            this.$wire.cerrarDetalle();
        }
    }"
    x-init="$el.showModal()" x-on:cancel.prevent="closeDetails()"
    aria-labelledby="equipo-detalle-titulo"
    class="m-auto h-[min(46rem,calc(100dvh-2rem))] w-[calc(100%-2rem)] max-w-[43rem] flex-col overflow-hidden rounded-2xl border border-line bg-surface text-ink shadow-xl backdrop:bg-black/50 open:flex">
    <header class="shrink-0 border-b border-line bg-canvas/50 px-4 pt-4 pb-3 sm:px-5">
        <div class="flex items-start gap-3">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface text-muted">
                <x-icon name="devices" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 id="equipo-detalle-titulo" class="text-base font-semibold wrap-anywhere sm:text-lg">
                        {{ $equipo->tipoEquipo->nombre }} {{ $equipo->marca->nombre }}
                    </h2>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-gray-100 text-gray-600' => $estado === 'Inactivo',
                        'bg-amber-100 text-amber-800' => $estado === 'Prestado',
                        'bg-green-100 text-green-800' => $estado === 'Disponible',
                    ])>{{ $estado }}</span>
                </div>
                <p class="mt-1 text-xs wrap-anywhere text-muted">
                    Inventario #{{ $equipo->numero_inventario }}@if (filled($equipo->modelo)) · {{ $equipo->modelo }}@endif
                </p>
            </div>
            <button type="button" autofocus aria-label="Cerrar ficha del equipo" x-on:click="closeDetails()"
                class="icon-button rounded-full"><x-icon name="close" class="size-4" /></button>
        </div>

        <div role="tablist" aria-label="Ficha del equipo" class="mt-3 grid grid-cols-3 gap-1 sm:gap-2"
            x-on:keydown.left.prevent="moveTab($event, -1)" x-on:keydown.right.prevent="moveTab($event, 1)"
            x-on:keydown.home.prevent="$refs.general.focus()" x-on:keydown.end.prevent="$refs.movimientos.focus()">
            @foreach ($pestanas as $clave => $etiqueta)
                <button type="button" id="equipo-detalle-tab-{{ $clave }}" role="tab" x-ref="{{ $clave }}"
                    aria-controls="equipo-detalle-panel-{{ $clave }}" x-bind:aria-selected="tab === '{{ $clave }}'"
                    x-bind:tabindex="tab === '{{ $clave }}' ? 0 : -1"
                    x-on:focus="tab = '{{ $clave }}'" x-on:click="tab = '{{ $clave }}'"
                    class="min-h-10 cursor-pointer rounded-full px-2 py-1.5 text-xs font-medium text-muted hover:bg-surface aria-selected:bg-accent-soft aria-selected:text-accent sm:text-sm">
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>
    </header>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div id="equipo-detalle-panel-general" role="tabpanel" aria-labelledby="equipo-detalle-tab-general"
            x-show="tab === 'general'" tabindex="0" class="divide-y divide-line px-4 sm:px-5">
            @foreach (['Identificación del equipo' => $identificacion, 'Características adicionales' => $caracteristicas, 'Registro' => $registro] as $seccion => $campos)
                <section class="py-4 sm:py-5">
                    <h3 class="mb-3 text-sm font-semibold">{{ $seccion }}</h3>
                    <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                        @foreach ($campos as $etiqueta => $valor)
                            <div class="min-w-0">
                                <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                                <dd class="mt-0.5 text-sm font-medium wrap-anywhere">{{ filled($valor) ? $valor : 'Sin registrar' }}</dd>
                            </div>
                        @endforeach
                        @if ($seccion === 'Características adicionales')
                            <div class="min-w-0 sm:col-span-2">
                                <dt class="text-xs text-muted">Observaciones</dt>
                                <dd @class(['mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap', 'text-muted italic' => blank($equipo->observaciones)])>{{ filled($equipo->observaciones) ? $equipo->observaciones : 'Sin observaciones registradas.' }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endforeach
        </div>

        <div id="equipo-detalle-panel-tecnico" role="tabpanel" aria-labelledby="equipo-detalle-tab-tecnico"
            x-show="tab === 'tecnico'" style="display: none" tabindex="0" class="px-4 py-5 sm:px-5">
            <div class="flex items-start gap-3 rounded-xl border border-line bg-canvas/60 p-4">
                <x-icon name="tools" class="mt-0.5 text-muted" />
                <div>
                    <h3 class="text-sm font-semibold">Historial técnico pendiente de integración</h3>
                    <p class="mt-1 text-sm text-muted">Las reparaciones y los mantenimientos aparecerán aquí cuando esté disponible el módulo de Taller.</p>
                </div>
            </div>
        </div>

        <div id="equipo-detalle-panel-movimientos" role="tabpanel" aria-labelledby="equipo-detalle-tab-movimientos"
            x-show="tab === 'movimientos'" style="display: none" tabindex="0" class="px-4 py-5 sm:px-5">
            @if ($movimientos->isEmpty())
                <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">Este equipo aún no tiene movimientos registrados.</p>
            @else
                <ol class="divide-y divide-line rounded-xl border border-line">
                    @foreach ($movimientos as $movimiento)
                        @php($fecha = \Illuminate\Support\Carbon::parse($movimiento->fecha))
                        <li wire:key="equipo-movimiento-{{ $movimiento->fuente }}-{{ $movimiento->origen_id }}" class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $acciones[$movimiento->tipo] }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $movimiento->responsable ?? 'Usuario no disponible' }}</p>
                            </div>
                            <time datetime="{{ $fecha->toIso8601String() }}" class="text-xs text-muted">{{ $fecha->format('d/m/Y H:i') }}</time>
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($movimientos->hasPages())
                <div class="mt-4">
                    <x-table-pagination :paginator="$movimientos" label="Paginación de movimientos del equipo" />
                </div>
            @endif
        </div>
    </div>

    <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-line bg-canvas/50 px-4 py-3 sm:px-5">
        <button type="button" wire:click="editarEquipo({{ $equipo->id }})" wire:loading.attr="disabled" wire:target="editarEquipo"
            class="secondary-button rounded-full"><x-icon name="edit" class="size-4" /> Editar equipo</button>
        <button type="button" x-show="tab !== 'movimientos'" x-on:click="tab = 'movimientos'; $refs.movimientos.focus()"
            class="primary-button rounded-full"><x-icon name="movements" class="size-4" /> Ver movimientos</button>
    </footer>
</dialog>
