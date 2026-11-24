<div>

    {{-- Encabezado --}}
    <div class="mb-6 flex items-center justify-between">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Recargas de tinta
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro de recargas realizadas a impresoras.
            </p>

        </div>


        <button
            type="button"
            wire:click="nuevaRecarga"
            class="rounded-full bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva recarga
        </button>

    </div>


    {{-- Mensaje --}}
    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    @php
        /** @var string $filtroEquipo */
        /** @var string $filtroColor */
        /** @var string $filtroUsuario */
        /** @var string $orden */

        $columns = \App\Services\RecargasTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;
        $selects = [
            'filtroEquipo' => ['Impresora', $printerOptions],
            'filtroColor' => ['Color de tinta', $colorOptions],
            'filtroUsuario' => ['Técnico', $userOptions],
            'orden' => ['Orden por fecha', $orders],
        ];
        $activeFilters = array_filter([
            'filtroEquipo' => isset($printerOptions[$filtroEquipo]) ? 'Impresora: '.$printerOptions[$filtroEquipo] : null,
            'filtroColor' => $filtroColor !== '' ? 'Color: '.$filtroColor : null,
            'filtroUsuario' => isset($userOptions[$filtroUsuario]) ? 'Técnico: '.$userOptions[$filtroUsuario] : null,
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
        ]);
    @endphp

    <x-data-table id="recargas-tinta" label="Historial de recargas" :columns="$columns"
        :show-columns="false" :sortable="false" :show-actions="false"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar recargas"
        search-placeholder="Impresora, inventario, tinta, color, observaciones o técnico...">
        <x-slot:filters>
            @foreach ($selects as $field => [$label, $options])
                <label wire:key="recargas-select-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <select wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="recargas-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-full border border-line bg-surface px-3 text-sm text-ink">
                        @if ($field !== 'orden')
                            <option value="">Todos</option>
                        @endif
                        @foreach ($options as $value => $option)
                            <option value="{{ $value }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
                <label wire:key="recargas-date-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="recargas-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="recargas-tinta" :action="route('inventario.tintas.recargas.exportar')" :buscar="$buscar"
                :visible-columns="array_keys($columns)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="recargas-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $recargas->count() }} de {{ $recargas->total() }} recargas encontradas
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="recargas-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>
        <x-slot:content>
            <div class="space-y-3 p-3 sm:p-4">
                @forelse ($recargas as $recarga)
                    @include('livewire.inventario.tintas.recargas.tarjeta-historial', ['recarga' => $recarga])
                @empty
                    <p class="rounded-xl border border-line bg-canvas px-4 py-10 text-center text-sm text-muted">
                        {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver las recargas.' : 'No se encontraron recargas de tinta.' }}
                    </p>
                @endforelse
            </div>
        </x-slot:content>
        <x-slot:pagination>
            <x-table-pagination :paginator="$recargas" label="Paginación de recargas de tinta" />
        </x-slot:pagination>
    </x-data-table>


    {{-- Modal --}}
    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl">


                {{-- Cabecera modal --}}
                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            Nueva recarga de tinta
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Puede registrar varios colores en una sola operación.
                        </p>

                    </div>


                    <button type="button" wire:click="cerrarModal" autofocus aria-label="Cerrar"
                        class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"><x-icon name="close" class="size-4" />
                    </button>

                </div>


                <form
                    wire:submit="guardarRecarga"
                    class="space-y-6"
                >


                    {{-- Impresora --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Impresora *
                        </label>


                        <select
                            wire:model="equipoId"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                            <option value="">
                                Seleccione una impresora...
                            </option>


                            @foreach ($equipos as $equipo)

                                <option value="{{ $equipo->id }}">

                                    {{ $equipo->marca?->nombre }}

                                    {{ $equipo->modelo }}

                                    — Inv.
                                    {{ $equipo->numero_inventario }}

                                    @if ($equipo->departamento)

                                        — {{ $equipo->departamento->nombre }}

                                    @endif

                                </option>

                            @endforeach

                        </select>


                        @error('equipoId')

                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- Colores --}}
                    <div>

                        <div class="mb-3 flex items-center justify-between">

                            <div>

                                <h3 class="font-semibold text-gray-800">
                                    Tintas utilizadas
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Agregue una fila por cada color recargado.
                                </p>

                            </div>


                            <button
                                type="button"
                                wire:click="agregarDetalle"
                                class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50"
                            >
                                + Agregar otro color
                            </button>

                        </div>


                        <div class="space-y-4">


                            @foreach ($detalles as $indice => $detalle)

                                <div
                                    wire:key="detalle-recarga-{{ $indice }}"
                                    class="rounded-xl border border-gray-200 p-4"
                                >


                                    <div class="mb-4 flex items-center justify-between">

                                        <span class="text-sm font-semibold text-gray-700">
                                            Color {{ $indice + 1 }}
                                        </span>


                                        @if (count($detalles) > 1)

                                            <button
                                                type="button"
                                                wire:click="eliminarDetalle({{ $indice }})"
                                                class="text-sm font-medium text-red-600 hover:text-red-800"
                                            >
                                                Eliminar
                                            </button>

                                        @endif

                                    </div>


                                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">


                                        {{-- Tinta --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Tinta *
                                            </label>


                                            <select
                                                wire:model="detalles.{{ $indice }}.tinta_id"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2"
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>


                                                @foreach ($tintas as $tinta)

                                                    @php

                                                        $existencia =
                                                            $tinta->existencia_equivalente;

                                                    @endphp


                                                    <option value="{{ $tinta->id }}">

                                                        {{ $tinta->nombre }}

                                                        — {{ $tinta->color }}

                                                        — {{ number_format(
                                                            $existencia,
                                                            2
                                                        ) }} bot.

                                                    </option>

                                                @endforeach

                                            </select>


                                            @error("detalles.$indice.tinta_id")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Antes --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Nivel antes *
                                            </label>


                                            <div class="relative">

                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    wire:model.live="detalles.{{ $indice }}.porcentaje_antes"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9"
                                                >

                                                <span class="absolute right-3 top-2 text-gray-500">
                                                    %
                                                </span>

                                            </div>


                                            @error("detalles.$indice.porcentaje_antes")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Agregado --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Agregado *
                                            </label>


                                            <div class="relative">

                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    max="100"
                                                    step="0.01"
                                                    wire:model.live="detalles.{{ $indice }}.porcentaje_agregado"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9"
                                                >

                                                <span class="absolute right-3 top-2 text-gray-500">
                                                    %
                                                </span>

                                            </div>


                                            @error("detalles.$indice.porcentaje_agregado")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Después --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Nivel después
                                            </label>


                                            @php

                                                $antes =
                                                    (float) (
                                                        $detalle['porcentaje_antes']
                                                        ?? 0
                                                    );

                                                $agregado =
                                                    (float) (
                                                        $detalle['porcentaje_agregado']
                                                        ?? 0
                                                    );

                                                $despues =
                                                    $antes + $agregado;

                                            @endphp


                                            <div
                                                class="rounded-lg border px-3 py-2 font-semibold
                                                {{ $despues > 100
                                                    ? 'border-red-300 bg-red-50 text-red-600'
                                                    : 'border-green-200 bg-green-50 text-green-700'
                                                }}"
                                            >

                                                {{ rtrim(
                                                    rtrim(
                                                        number_format(
                                                            $despues,
                                                            2,
                                                            '.',
                                                            ''
                                                        ),
                                                        '0'
                                                    ),
                                                    '.'
                                                ) }} %

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    </div>


                    {{-- Observaciones --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>


                        <textarea
                            wire:model="observaciones"
                            rows="3"
                            placeholder="Información adicional sobre la recarga..."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>


                        @error('observaciones')

                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- Botones --}}
                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">


                        <button
                            type="button"
                            wire:click="cerrarModal"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>


                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        >
                            Registrar recarga
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>
