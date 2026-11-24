<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Insumos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de insumos del Departamento de Informática.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearInsumo"
            class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo insumo
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    @php
        $statusOptions = ['activo' => 'Activos', 'inactivo' => 'Inactivos'];
        $activeStatus = $statusOptions[$filtroEstado] ?? null;
    @endphp

    <x-data-table
        id="insumos"
        label="Tabla de insumos"
        :columns="\App\Livewire\Inventario\Insumos\Index::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar insumo"
        search-placeholder="Nombre, marca o categoría..."
        default-order-label="orden por nombre"
        :export-disabled="$dateErrors->isNotEmpty()"
        :collapsible-filters="true"
        :active-filter-count="($activeStatus === null ? 0 : 1) + (int) ($desde !== '') + (int) ($hasta !== '')"
    >
        <x-slot:export>
            <x-table-export id="insumos" :action="route('inventario.insumos.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :show-date-fields="false" :export-filters="['filtroEstado' => $filtroEstado, 'desde' => $desde, 'hasta' => $hasta]" />
        </x-slot:export>
        <x-slot:filters>
            <x-searchable-select id="insumos-filter-estado" label="Estado" :options="$statusOptions"
                model="filtroEstado" all-label="Todos" class="sm:col-span-2" />
            <x-table-date-filters id="insumos" :errors="$dateErrors" />
        </x-slot:filters>
        <x-slot:summary>
            Mostrando {{ $insumos->count() }} de {{ $insumos->total() }} insumos encontrados
        </x-slot:summary>

        <x-slot:filterChips>
            <x-table-date-chips id="insumos" :desde="$desde" :hasta="$hasta" />
            @if ($activeStatus !== null)
                <button type="button" wire:key="insumos-filter-chip-estado" wire:click="removeStatusFilter"
                    wire:loading.attr="disabled" aria-label="Quitar filtro Estado: {{ $activeStatus }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">Estado: {{ $activeStatus }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endif
        </x-slot:filterChips>

        @forelse ($insumos as $insumo)

            @php
                $cantidad = (float) $insumo->cantidad_actual;

                $unidadCantidad =
                    $cantidad == 1
                        ? $insumo->unidadMedida->nombre_singular
                        : $insumo->unidadMedida->nombre_plural;

                $stockMinimo =
                    (float) $insumo->stock_minimo;

                $unidadStock =
                    $stockMinimo == 1
                        ? $insumo->unidadMedida->nombre_singular
                        : $insumo->unidadMedida->nombre_plural;

                $stockBajo =
                    $cantidad <= $stockMinimo;
            @endphp


            <tr wire:key="insumos-row-{{ $insumo->id }}" wire:click="verInsumo({{ $insumo->id }})"
                class="cursor-pointer hover:bg-canvas/60 focus-within:bg-canvas/60">

                @if (in_array('nombre', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-nombre" class="px-5 py-4 font-medium text-gray-800">
                        {{ $insumo->nombre }}
                    </td>
                @endif


                @if (in_array('categoria', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-categoria" class="px-5 py-4 text-gray-600">
                        {{ $insumo->categoria->nombre }}
                    </td>
                @endif


                @if (in_array('marca', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-marca" class="px-5 py-4 text-gray-600">
                        {{ $insumo->marca?->nombre ?? '—' }}
                    </td>
                @endif


                @if (in_array('cantidad_actual', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-cantidad_actual" class="px-5 py-4">

                        <span
                            class="{{ $stockBajo
                                ? 'font-semibold text-red-600'
                                : 'text-gray-700'
                            }}"
                        >
                            {{ (float) number_format($cantidad, 2, '.', '') }}

                            {{ $unidadCantidad }}
                        </span>


                        @if ($stockBajo)

                            <div class="mt-1 text-xs text-red-600">
                                Stock bajo
                            </div>

                        @endif

                    </td>
                @endif


                @if (in_array('stock_minimo', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-stock_minimo" class="px-5 py-4 text-gray-600">

                        {{ (float) number_format($stockMinimo, 2, '.', '') }}

                        {{ $unidadStock }}

                    </td>
                @endif


                @if (in_array('activo', $visibleColumns, true))
                    <td wire:key="insumos-cell-{{ $insumo->id }}-activo" class="px-5 py-4">

                        @if ($insumo->activo)

                            <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                Activo
                            </span>

                        @else

                            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                Inactivo
                            </span>

                        @endif

                    </td>
                @endif


                <td class="cursor-default px-4 py-2 text-right">

                    <div class="flex justify-end gap-1">
                        <button type="button" id="ver-insumo-{{ $insumo->id }}" wire:click.stop="verInsumo({{ $insumo->id }})"
                            aria-haspopup="dialog" class="icon-button" title="Ver insumo" aria-label="Ver insumo {{ $insumo->nombre }}"><x-icon name="eye" /></button>
                        <button type="button" wire:click.stop="editarInsumo({{ $insumo->id }})" class="icon-button text-blue-600"
                            title="Editar insumo" aria-label="Editar insumo {{ $insumo->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click.stop="cambiarEstado({{ $insumo->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $insumo->activo ? 'desactivar' : 'activar' }} el insumo {{ $insumo->nombre }}?"
                            class="icon-button {{ $insumo->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $insumo->activo ? 'Desactivar insumo' : 'Activar insumo' }}"
                            aria-label="{{ $insumo->activo ? 'Desactivar' : 'Activar' }} insumo {{ $insumo->nombre }}"><x-icon name="power" /></button>
                    </div>

                </td>

            </tr>


        @empty

            <tr>

                <td
                    colspan="{{ count($visibleColumns) + 1 }}"
                    class="px-6 py-12 text-center text-gray-500"
                >
                    No se encontraron insumos registrados.
                </td>

            </tr>

        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$insumos" label="Paginación de insumos" />
        </x-slot:pagination>
    </x-data-table>


    @if ($insumoDetalle)
        @include('livewire.inventario.insumos.detalle', ['insumo' => $insumoDetalle, 'movimientos' => $movimientosInsumo])
    @endif

    @if ($mostrarModal)
        @include('livewire.inventario.insumos.formulario')
    @endif

    @if ($mostrarModalCatalogo)
        @include('livewire.inventario.catalogo-rapido', ['prefijo' => 'insumo', 'ejemploCategoria' => 'Ej. Redes, Conectividad', 'ejemploMarca' => 'Ej. Cisco, Panduit'])
    @endif
</div>
