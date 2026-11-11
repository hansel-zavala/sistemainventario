<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Equipos tecnológicos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de los equipos tecnológicos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearEquipo"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo equipo
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    @error('estadoEquipo')
        <p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @php
        $filterLabels = ['categoria' => 'Categoría', 'tipo' => 'Tipo de equipo', 'marca' => 'Marca', 'departamento' => 'Procedencia'];
        $statusLabels = ['disponible' => 'Disponible', 'prestado' => 'Prestado', 'inactivo' => 'Inactivo'];
    @endphp

    <x-data-table
        id="equipos"
        label="Tabla de equipos tecnológicos"
        :columns="\App\Services\EquiposTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar equipo"
        search-placeholder="Inventario, serie, marca, modelo o tipo..."
        :collapsible-filters="true"
        :active-filter-count="count(array_filter($filters, fn ($value) => filled($value)))"
    >
        <x-slot:export>
            <x-table-export id="equipos" :action="route('inventario.equipos.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :export-filters="$filters" />
        </x-slot:export>

        <x-slot:filters>
            @foreach ($filterLabels as $name => $label)
                <label wire:key="filter-{{ $name }}" class="text-sm font-medium text-muted">{{ $label }}
                    <select wire:model.live="filters.{{ $name }}" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                        <option value="">{{ $name === 'tipo' ? 'Todos' : 'Todas' }}</option>
                        @foreach ($filterOptions[$name] as $option)
                            <option wire:key="filter-{{ $name }}-{{ $option->id }}" value="{{ $option->id }}">{{ $option->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <label class="text-sm font-medium text-muted sm:col-span-2">Estado
                <select wire:model.live="filters.estado" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    @foreach ($statusLabels as $value => $label)
                        <option wire:key="equipos-estado-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>

        <x-slot:summary>
            Mostrando {{ $equipos->count() }} de {{ $equipos->total() }} equipos encontrados
        </x-slot:summary>

        <x-slot:filterChips>
            @foreach ($filterLabels + ['estado' => 'Estado'] as $name => $label)
                @continue(blank($filters[$name] ?? null))
                @php
                    $value = $name === 'estado'
                        ? ($statusLabels[$filters[$name]] ?? null)
                        : $filterOptions[$name]->firstWhere('id', $filters[$name])?->nombre;
                @endphp
                @continue($value === null)
                <button type="button" wire:key="equipos-filter-chip-{{ $name }}" wire:click="removeFilter('{{ $name }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}: {{ $value }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}: {{ $value }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($equipos as $equipo)
            <tr wire:key="equipment-{{ $equipo->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\EquiposTable::COLUMNS as $column => $label)
                    @if (in_array($column, $visibleColumns, true))
                        <td wire:key="cell-{{ $equipo->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                            @if ($column === 'tipo_equipo')
                                <div class="font-medium">{{ $equipo->tipoEquipo->nombre }}</div>
                                <div class="mt-0.5 text-xs text-muted">{{ $equipo->categoria->nombre }}</div>
                            @elseif ($column === 'disponibilidad')
                                <span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-gray-100 text-gray-600' => !$equipo->activo, 'bg-amber-100 text-amber-800' => $equipo->activo && $equipo->esta_prestado, 'bg-green-100 text-green-800' => $equipo->activo && !$equipo->esta_prestado])>
                                    {{ \App\Services\EquiposTable::status($equipo) }}
                                </span>
                            @else
                                {{ \App\Services\EquiposTable::row($equipo, [$column])[0] }}
                            @endif
                        </td>
                    @endif
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarEquipo({{ $equipo->id }})" class="icon-button text-blue-600"
                            title="Editar equipo" aria-label="Editar equipo {{ $equipo->numero_inventario }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $equipo->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $equipo->activo ? 'desactivar' : 'reactivar' }} el equipo {{ $equipo->numero_inventario }}?"
                            class="icon-button {{ $equipo->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $equipo->activo ? 'Desactivar equipo' : 'Reactivar equipo' }}"
                            aria-label="{{ $equipo->activo ? 'Desactivar' : 'Reactivar' }} equipo {{ $equipo->numero_inventario }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron equipos con estos filtros.</td></tr>
        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$equipos" label="Paginación de equipos" />
        </x-slot:pagination>
    </x-data-table>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $equipoId
                                ? 'Editar equipo'
                                : 'Nuevo equipo'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información básica del equipo.
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

                @if (session()->has('mensajeCatalogo'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('mensajeCatalogo') }}
                    </div>
                @endif


                <form
                    wire:submit="guardarEquipo"
                    class="space-y-5"
                >

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


                        {{-- Categoría --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Categoría *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="categoriaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($categorias as $categoria)
                                        <option value="{{ $categoria->id }}">
                                            {{ $categoria->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('categoria')"
                                    title="Nueva categoría"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('categoriaId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Tipo --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Tipo de equipo *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="tipoEquipoId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($tiposEquipo as $tipo)
                                        <option value="{{ $tipo->id }}">
                                            {{ $tipo->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('tipo_equipo')"
                                    title="Nuevo tipo de equipo"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('tipoEquipoId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Departamento --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Departamento de procedencia *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="departamentoId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($departamentos as $departamento)
                                        <option value="{{ $departamento->id }}">
                                            {{ $departamento->nombre }}
                                            @if ($departamento->sigla)
                                                ({{ $departamento->sigla }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('departamento')"
                                    title="Nuevo departamento"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('departamentoId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Marca --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Marca *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="marcaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($marcas as $marca)
                                        <option value="{{ $marca->id }}">
                                            {{ $marca->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('marca')"
                                    title="Nueva marca"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('marcaId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Modelo --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Modelo
                            </label>

                            <input
                                type="text"
                                wire:model="modelo"
                                placeholder="Ej. Latitude 5420"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('modelo')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Color --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Color
                            </label>

                            <input
                                type="text"
                                wire:model="color"
                                placeholder="Ej. Negro"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('color')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Serie --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Número de serie
                            </label>

                            <input
                                type="text"
                                wire:model="numeroSerie"
                                placeholder="Serie del fabricante"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('numeroSerie')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Inventario --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Número de inventario *
                            </label>

                            <input
                                type="text"
                                wire:model="numeroInventario"
                                placeholder="Número de inventario municipal"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('numeroInventario')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                    </div>


                    {{-- Observaciones --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="4"
                            placeholder="Observaciones adicionales del equipo..."
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
                            {{ $equipoId
                                ? 'Guardar cambios'
                                : 'Registrar equipo'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif


    {{-- Modal de catálogo rápido --}}
    @if ($mostrarModalCatalogo)

        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-4 flex items-center justify-between">

                    <div>
                        <h3 class="text-lg font-bold text-gray-800">
                            @if ($tipoCatalogo === 'categoria')
                                Nueva categoría
                            @elseif ($tipoCatalogo === 'tipo_equipo')
                                Nuevo tipo de equipo
                            @elseif ($tipoCatalogo === 'departamento')
                                Nuevo departamento
                            @elseif ($tipoCatalogo === 'marca')
                                Nueva marca
                            @else
                                Nuevo registro
                            @endif
                        </h3>

                        <p class="text-xs text-gray-500">
                            Ingrese el nombre para registrarlo rápidamente.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModalCatalogo"
                        class="text-2xl text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>

                </div>

                <form
                    wire:submit="guardarCatalogoRapido"
                    class="space-y-4"
                >

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nombre *
                        </label>

                        <input
                            type="text"
                            wire:model="nuevoNombreCatalogo"
                            placeholder="Nombre del registro"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            autofocus
                        >

                        @error('nuevoNombreCatalogo')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    @if ($tipoCatalogo === 'departamento')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Sigla
                            </label>

                            <input
                                type="text"
                                wire:model="nuevaSiglaDepartamento"
                                placeholder="Ej. RRHH"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            >

                            @error('nuevaSiglaDepartamento')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            wire:click="cerrarModalCatalogo"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Guardar
                        </button>
                    </div>

                </form>

            </div>

        </div>

    @endif

</div>
