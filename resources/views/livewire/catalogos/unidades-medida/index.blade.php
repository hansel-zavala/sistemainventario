<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Unidades de medida
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de unidades utilizadas en herramientas e insumos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearUnidadMedida"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva unidad
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <x-data-table
        id="unidades-medida"
        label="Tabla de unidades de medida"
        :columns="\App\Services\UnidadesMedidaTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar unidad"
        search-placeholder="Nombre o abreviatura de la unidad..."
        default-order-label="orden por singular"
    >
        <x-slot:export>
            <x-table-export id="unidades-medida" :action="route('catalogos.unidades-medida.exportar')"
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
            {{ $unidades->total() }} unidades · {{ $sortColumn ? 'Orden: '.\App\Services\UnidadesMedidaTable::COLUMNS[$sortColumn].' ('.($sortDirection === 'asc' ? 'ascendente' : 'descendente').')' : 'Orden por singular' }}
        </x-slot:summary>

        @forelse ($unidades as $unidad)
            <tr wire:key="unidades-medida-row-{{ $unidad->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\UnidadesMedidaTable::values($unidad) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="unidades-medida-cell-{{ $unidad->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $unidad->activo, 'bg-red-50 text-red-700' => ! $unidad->activo])>
                                {{ $value }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarUnidadMedida({{ $unidad->id }})" class="icon-button text-blue-600"
                            title="Editar unidad" aria-label="Editar unidad {{ $unidad->nombre_singular }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $unidad->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $unidad->activo ? 'desactivar' : 'activar' }} la unidad {{ $unidad->nombre_singular }}?"
                            class="icon-button {{ $unidad->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $unidad->activo ? 'Desactivar unidad' : 'Activar unidad' }}"
                            aria-label="{{ $unidad->activo ? 'Desactivar' : 'Activar' }} unidad {{ $unidad->nombre_singular }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron unidades registradas.</td></tr>
        @endforelse

        <x-slot:pagination>{{ $unidades->links() }}</x-slot:pagination>
    </x-data-table>

    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $unidadMedidaId
                                ? 'Editar unidad de medida'
                                : 'Nueva unidad de medida'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información de la unidad.
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
                    wire:submit="guardarUnidadMedida"
                    class="space-y-4"
                >

                    <div>

                        <label
                            for="nombreSingular"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre singular *
                        </label>

                        <input
                            id="nombreSingular"
                            type="text"
                            wire:model="nombreSingular"
                            placeholder="Ej. unidad"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombreSingular')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="nombrePlural"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre plural *
                        </label>

                        <input
                            id="nombrePlural"
                            type="text"
                            wire:model="nombrePlural"
                            placeholder="Ej. unidades"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombrePlural')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="abreviatura"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Abreviatura
                        </label>

                        <input
                            id="abreviatura"
                            type="text"
                            wire:model="abreviatura"
                            placeholder="Ej. und"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Este campo es opcional.
                        </p>

                        @error('abreviatura')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div class="space-y-3">

                        <div class="flex items-center">

                            <input
                                id="permiteDecimales"
                                type="checkbox"
                                wire:model="permiteDecimales"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="permiteDecimales"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Permite cantidades decimales
                            </label>

                        </div>


                        <div class="flex items-center">

                            <input
                                id="activoUnidad"
                                type="checkbox"
                                wire:model="activoUnidad"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoUnidad"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Unidad de medida activa
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
                            {{ $unidadMedidaId
                                ? 'Guardar cambios'
                                : 'Crear unidad'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>
