<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Tipos de equipo
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de los tipos de equipos tecnológicos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearTipoEquipo"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo tipo
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <x-data-table
        id="tipos-equipo"
        label="Tabla de tipos de equipo"
        :columns="\App\Services\TiposEquipoTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar tipo de equipo"
        search-placeholder="Nombre del tipo de equipo..."
        default-order-label="orden por nombre"
    >
        <x-slot:export>
            <x-table-export id="tipos-equipo" :action="route('catalogos.tipos-equipo.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :export-filters="['filtroEstado' => $filtroEstado]" />
        </x-slot:export>

        <x-slot:filters>
            <label class="min-w-40 flex-1 text-xs font-medium text-muted">Estado
                <select wire:model.live="filtroEstado" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    <option value="activo">Activos</option>
                    <option value="inactivo">Inactivos</option>
                </select>
            </label>
            <button type="button" wire:click="clearFilters" class="secondary-button">Limpiar</button>
        </x-slot:filters>

        <x-slot:summary>
            {{ $tiposEquipo->total() }} tipos de equipo · {{ $sortColumn ? 'Orden: '.\App\Services\TiposEquipoTable::COLUMNS[$sortColumn].' ('.($sortDirection === 'asc' ? 'ascendente' : 'descendente').')' : 'Orden por nombre' }}
        </x-slot:summary>

        @forelse ($tiposEquipo as $tipoEquipo)
            <tr wire:key="tipos-equipo-row-{{ $tipoEquipo->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\TiposEquipoTable::values($tipoEquipo) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="tipos-equipo-cell-{{ $tipoEquipo->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $tipoEquipo->activo, 'bg-red-50 text-red-700' => ! $tipoEquipo->activo])>
                                {{ $value }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarTipoEquipo({{ $tipoEquipo->id }})" class="icon-button text-blue-600"
                            title="Editar tipo de equipo" aria-label="Editar tipo de equipo {{ $tipoEquipo->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $tipoEquipo->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $tipoEquipo->activo ? 'desactivar' : 'activar' }} el tipo de equipo {{ $tipoEquipo->nombre }}?"
                            class="icon-button {{ $tipoEquipo->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $tipoEquipo->activo ? 'Desactivar tipo de equipo' : 'Activar tipo de equipo' }}"
                            aria-label="{{ $tipoEquipo->activo ? 'Desactivar' : 'Activar' }} tipo de equipo {{ $tipoEquipo->nombre }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron tipos de equipo registrados.</td></tr>
        @endforelse

        <x-slot:pagination>{{ $tiposEquipo->links() }}</x-slot:pagination>
    </x-data-table>

    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $tipoEquipoId
                                ? 'Editar tipo de equipo'
                                : 'Nuevo tipo de equipo'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información del tipo de equipo.
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
                    wire:submit="guardarTipoEquipo"
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
                            placeholder="Ej. Impresora"
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
                                id="activoTipoEquipo"
                                type="checkbox"
                                wire:model="activoTipoEquipo"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoTipoEquipo"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Tipo de equipo activo
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
                            {{ $tipoEquipoId
                                ? 'Guardar cambios'
                                : 'Crear tipo'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>