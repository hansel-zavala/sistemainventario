<div>

    {{-- Encabezado --}}
    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Categorías
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de categorías del inventario.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearCategoria"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva categoría
        </button>

    </div>


    {{-- Mensaje --}}
    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    @php
        $filterFields = [
            'filtroTipo' => ['label' => 'Aplica a', 'options' => \App\Services\CategoriasTable::TYPES, 'value' => $filtroTipo],
            'filtroEstado' => ['label' => 'Estado', 'options' => ['activo' => 'Activas', 'inactivo' => 'Inactivas'], 'value' => $filtroEstado],
        ];
        $activeFilters = array_filter($filterFields, fn ($field) => isset($field['options'][$field['value']]));
    @endphp

    <x-data-table
        id="categorias"
        label="Tabla de categorías"
        :columns="\App\Services\CategoriasTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar categoría"
        search-placeholder="Nombre de categoría..."
        default-order-label="orden por nombre"
        :collapsible-filters="true"
        :active-filter-count="count($activeFilters)"
    >
        <x-slot:export>
            <x-table-export id="categorias" :action="route('catalogos.categorias.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :export-filters="['filtroEstado' => $filtroEstado, 'filtroTipo' => $filtroTipo]" />
        </x-slot:export>
        <x-slot:filters>
            @foreach ($filterFields as $name => $field)
                <label wire:key="categorias-filter-{{ $name }}" class="text-sm font-medium text-muted">
                    {{ $field['label'] }}
                    <select wire:model.live="{{ $name }}" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                        <option value="">Todos</option>
                        @foreach ($field['options'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:summary>
            Mostrando {{ $categorias->count() }} de {{ $categorias->total() }} categorías encontradas
        </x-slot:summary>

        <x-slot:filterChips>
            @foreach ($activeFilters as $name => $field)
                @php
                    $chipLabel = $field['label'].': '.$field['options'][$field['value']];
                @endphp
                <button type="button" wire:key="categorias-filter-chip-{{ $name }}" wire:click="removeFilter('{{ $name }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $chipLabel }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $chipLabel }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($categorias as $categoria)
            <tr wire:key="categorias-row-{{ $categoria->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\CategoriasTable::values($categoria) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="categorias-cell-{{ $categoria->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $categoria->activo, 'bg-red-50 text-red-700' => ! $categoria->activo])>
                                {{ $value }}
                            </span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarCategoria({{ $categoria->id }})" class="icon-button text-blue-600"
                            title="Editar categoría" aria-label="Editar categoría {{ $categoria->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click="cambiarEstado({{ $categoria->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $categoria->activo ? 'desactivar' : 'activar' }} la categoría {{ $categoria->nombre }}?"
                            class="icon-button {{ $categoria->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $categoria->activo ? 'Desactivar categoría' : 'Activar categoría' }}"
                            aria-label="{{ $categoria->activo ? 'Desactivar' : 'Activar' }} categoría {{ $categoria->nombre }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron categorías.</td></tr>
        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$categorias" label="Paginación de categorías" />
        </x-slot:pagination>
    </x-data-table>

    {{-- Modal --}}
    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $categoriaId
                                ? 'Editar categoría'
                                : 'Nueva categoría'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información de la categoría.
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
                    wire:submit="guardarCategoria"
                    class="space-y-4"
                >

                    {{-- Nombre --}}
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
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            placeholder="Ej. Equipos de impresión"
                        >

                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Tipo --}}
                    <div>

                        <label
                            for="aplicaA"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Aplica a *
                        </label>

                        <select
                            id="aplicaA"
                            wire:model="aplicaA"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                            <option value="equipo">
                                Equipos tecnológicos
                            </option>

                            <option value="herramienta">
                                Herramientas
                            </option>

                            <option value="insumo">
                                Insumos
                            </option>

                        </select>

                        @error('aplicaA')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Estado --}}
                    <div>

                        <div class="flex items-center">

                            <input
                                id="activoCategoria"
                                type="checkbox"
                                wire:model="activoCategoria"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoCategoria"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Categoría activa
                            </label>

                        </div>

                        @error('activoCategoria')
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
                            {{ $categoriaId
                                ? 'Guardar cambios'
                                : 'Crear categoría'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>
