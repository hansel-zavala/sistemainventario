<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Departamentos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de departamentos de procedencia.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearDepartamento"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo departamento
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <x-data-table
        id="departamentos"
        label="Tabla de departamentos"
        :columns="\App\Services\DepartamentosTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar departamento"
        search-placeholder="Nombre o sigla del departamento..."
        default-order-label="orden por nombre"
    >
        <x-slot:export>
            <x-table-export id="departamentos" :action="route('catalogos.departamentos.exportar')"
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
            {{ $departamentos->total() }} departamentos · {{ $sortColumn ? 'Orden: '.\App\Services\DepartamentosTable::COLUMNS[$sortColumn].' ('.($sortDirection === 'asc' ? 'ascendente' : 'descendente').')' : 'Orden por nombre' }}
        </x-slot:summary>

        @forelse ($departamentos as $departamento)
            <tr wire:key="departamentos-row-{{ $departamento->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\DepartamentosTable::values($departamento) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="departamentos-cell-{{ $departamento->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $departamento->activo, 'bg-red-50 text-red-700' => ! $departamento->activo])>
                                {{ $value }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarDepartamento({{ $departamento->id }})" class="icon-button text-blue-600"
                            title="Editar departamento" aria-label="Editar departamento {{ $departamento->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $departamento->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $departamento->activo ? 'desactivar' : 'activar' }} el departamento {{ $departamento->nombre }}?"
                            class="icon-button {{ $departamento->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $departamento->activo ? 'Desactivar departamento' : 'Activar departamento' }}"
                            aria-label="{{ $departamento->activo ? 'Desactivar' : 'Activar' }} departamento {{ $departamento->nombre }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron departamentos registrados.</td></tr>
        @endforelse

        <x-slot:pagination>{{ $departamentos->links() }}</x-slot:pagination>
    </x-data-table>

    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $departamentoId
                                ? 'Editar departamento'
                                : 'Nuevo departamento'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información del departamento.
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
                    wire:submit="guardarDepartamento"
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
                            placeholder="Ej. Recursos Humanos"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="sigla"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Sigla
                        </label>

                        <input
                            id="sigla"
                            type="text"
                            wire:model="sigla"
                            placeholder="Ej. RRHH"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Este campo es opcional.
                        </p>

                        @error('sigla')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <div class="flex items-center">

                            <input
                                id="activoDepartamento"
                                type="checkbox"
                                wire:model="activoDepartamento"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoDepartamento"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Departamento activo
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
                            {{ $departamentoId
                                ? 'Guardar cambios'
                                : 'Crear departamento'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>