<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Marcas
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de marcas utilizadas en el inventario.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearMarca"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva marca
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <x-data-table
        id="marcas"
        label="Tabla de marcas"
        :columns="\App\Services\MarcasTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar marca"
        search-placeholder="Nombre de la marca..."
        default-order-label="orden por nombre"
    >
        <x-slot:export>
            <x-table-export id="marcas" :action="route('catalogos.marcas.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :export-filters="['filtroEstado' => $filtroEstado]" />
        </x-slot:export>

        <x-slot:filters>
            <label class="min-w-40 flex-1 text-xs font-medium text-muted">Estado
                <select wire:model.live="filtroEstado" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    <option value="activo">Activas</option>
                    <option value="inactivo">Inactivas</option>
                </select>
            </label>
            <button type="button" wire:click="clearFilters" class="secondary-button">Limpiar</button>
        </x-slot:filters>

        <x-slot:summary>
            {{ $marcas->total() }} marcas · {{ $sortColumn ? 'Orden: '.\App\Services\MarcasTable::COLUMNS[$sortColumn].' ('.($sortDirection === 'asc' ? 'ascendente' : 'descendente').')' : 'Orden por nombre' }}
        </x-slot:summary>

        @forelse ($marcas as $marca)
            <tr wire:key="marcas-row-{{ $marca->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\MarcasTable::values($marca) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="marcas-cell-{{ $marca->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $marca->activo, 'bg-red-50 text-red-700' => ! $marca->activo])>
                                {{ $value }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarMarca({{ $marca->id }})" class="icon-button text-blue-600"
                            title="Editar marca" aria-label="Editar marca {{ $marca->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $marca->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $marca->activo ? 'desactivar' : 'activar' }} la marca {{ $marca->nombre }}?"
                            class="icon-button {{ $marca->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $marca->activo ? 'Desactivar marca' : 'Activar marca' }}"
                            aria-label="{{ $marca->activo ? 'Desactivar' : 'Activar' }} marca {{ $marca->nombre }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron marcas registradas.</td></tr>
        @endforelse

        <x-slot:pagination>{{ $marcas->links() }}</x-slot:pagination>
    </x-data-table>

    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $marcaId
                                ? 'Editar marca'
                                : 'Nueva marca'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información de la marca.
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
                    wire:submit="guardarMarca"
                    class="space-y-4"
                >

                    <div>

                        <label
                            for="nombre"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre *
                        </label>

                        <input
                            id="nombre"
                            type="text"
                            wire:model="nombre"
                            placeholder="Ej. Epson"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <div class="flex items-center">

                            <input
                                id="activoMarca"
                                type="checkbox"
                                wire:model="activoMarca"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoMarca"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Marca activa
                            </label>

                        </div>

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
                            {{ $marcaId
                                ? 'Guardar cambios'
                                : 'Crear marca'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>