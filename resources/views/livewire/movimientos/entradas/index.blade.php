<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Entradas de inventario
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro de artículos que ingresan al inventario.
            </p>
        </div>

        <button
            type="button"
            wire:click="nuevaEntrada"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva entrada
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    @php
        /** @var string $orden */
        /** @var string $tipoArticulo */
        /** @var string $destino */

        $types = \App\Services\MovimientosTable::TYPES;
        $columns = \App\Services\EntradasTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;

        $activeFilters = array_filter([
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'tipoArticulo' => isset($types[$tipoArticulo]) ? 'Tipo de artículo: '.$types[$tipoArticulo] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
        ]);
    @endphp

    <x-data-table id="entradas" label="Listado de entradas" :columns="$columns"
        :show-columns="false" :sortable="false" :show-actions="false"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar entradas"
        search-placeholder="Artículo, código, color, observaciones o usuario...">
        <x-slot:filters>
            <label class="text-sm font-medium text-muted sm:col-span-2">Tipo de artículo
                <select wire:model.live="tipoArticulo" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-2">Orden por fecha
                <select wire:model.live="orden" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    @foreach ($orders as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
                <label wire:key="entradas-filter-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="entradas-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="entradas" :action="route('movimientos.entradas.exportar')" :buscar="$buscar"
                :visible-columns="array_keys($columns)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="entradas-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $movimientos->count() }} de {{ $movimientos->total() }} entradas encontradas
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="entradas-filter-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>
        <x-slot:content>
            <div class="space-y-3 p-3 sm:p-4">
                @forelse ($movimientos as $movimiento)
                    @include('livewire.movimientos.entradas.tarjeta-historial', ['movimiento' => $movimiento])
                @empty
                    <p class="rounded-xl border border-line bg-canvas px-4 py-10 text-center text-sm text-muted">
                        {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver las entradas.' : 'No se encontraron entradas.' }}
                    </p>
                @endforelse
            </div>
        </x-slot:content>
        <x-slot:pagination>
            <x-table-pagination :paginator="$movimientos" label="Paginación de entradas" />
        </x-slot:pagination>
    </x-data-table>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            Nueva entrada de inventario
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Puede registrar varios artículos en una sola entrada.
                        </p>

                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModal"
                        class="text-2xl text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>

                </div>


                <form
                    wire:submit="guardarEntrada"
                    class="space-y-6"
                >

                    <div>

                        <div class="mb-3 flex items-center justify-between">

                            <div>

                                <h3 class="font-semibold text-gray-800">
                                    Artículos recibidos
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Agregue una fila por cada artículo.
                                </p>

                            </div>

                            <button
                                type="button"
                                wire:click="agregarDetalle"
                                class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50"
                            >
                                + Agregar artículo
                            </button>

                        </div>


                        <div class="space-y-4">

                            @foreach ($detalles as $indice => $detalle)

                                <div
                                    wire:key="entrada-detalle-{{ $indice }}"
                                    class="rounded-xl border border-gray-200 p-4"
                                >

                                    <div class="mb-4 flex items-center justify-between">

                                        <span class="text-sm font-semibold text-gray-700">
                                            Artículo {{ $indice + 1 }}
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


                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Tipo *
                                            </label>

                                            <select
                                                wire:model.live="detalles.{{ $indice }}.tipo_item"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>

                                                <option value="herramienta">
                                                    Herramienta
                                                </option>

                                                <option value="insumo">
                                                    Insumo
                                                </option>

                                                <option value="tinta">
                                                    Tinta
                                                </option>

                                            </select>

                                            @error("detalles.$indice.tipo_item")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror

                                        </div>


                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Artículo *
                                            </label>

                                            <select
                                                wire:model="detalles.{{ $indice }}.item_id"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                                @disabled(empty($detalle['tipo_item']))
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>


                                                @if ($detalle['tipo_item'] === 'herramienta')

                                                    @foreach ($herramientas as $herramienta)

                                                        <option value="{{ $herramienta->id }}">
                                                            {{ $herramienta->nombre }}
                                                        </option>

                                                    @endforeach

                                                @elseif ($detalle['tipo_item'] === 'insumo')

                                                    @foreach ($insumos as $insumo)

                                                        <option value="{{ $insumo->id }}">
                                                            {{ $insumo->nombre }}
                                                        </option>

                                                    @endforeach

                                                @elseif ($detalle['tipo_item'] === 'tinta')

                                                    @foreach ($tintas as $tinta)

                                                        <option value="{{ $tinta->id }}">

                                                            {{ $tinta->nombre }}
                                                            — {{ $tinta->color }}

                                                            @if ($tinta->marca)
                                                                — {{ $tinta->marca->nombre }}
                                                            @endif

                                                        </option>

                                                    @endforeach

                                                @endif

                                            </select>

                                            @error("detalles.$indice.item_id")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror

                                        </div>


                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">

                                                @if ($detalle['tipo_item'] === 'tinta')
                                                    Botellas recibidas *
                                                @else
                                                    Cantidad recibida *
                                                @endif

                                            </label>

                                            <input
                                                type="number"
                                                min="{{ $detalle['tipo_item'] === 'tinta' ? '1' : '0.01' }}"
                                                step="{{ $detalle['tipo_item'] === 'tinta' ? '1' : '0.01' }}"
                                                wire:model="detalles.{{ $indice }}.cantidad"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >

                                            @if ($detalle['tipo_item'] === 'tinta')

                                                <p class="mt-1 text-xs text-gray-500">
                                                    Registre únicamente botellas completas.
                                                </p>

                                            @endif

                                            @error("detalles.$indice.cantidad")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror

                                        </div>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    </div>


                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="3"
                            placeholder="Ej. Material recibido para abastecimiento del Departamento de Informática."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>

                        @error('observaciones')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


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
                            Registrar entrada
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>
