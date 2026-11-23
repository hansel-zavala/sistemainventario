<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Herramientas
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de herramientas del Departamento de Informática.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearHerramienta"
            class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva herramienta
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif

    @error('estadoHerramienta')
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $message }}
        </div>
    @enderror

    @php
        $filterFields = [
            'filtroTipo' => ['label' => 'Tipo de control', 'options' => ['individual' => 'Individual', 'cantidad' => 'Por cantidad'], 'value' => $filtroTipo],
            'filtroEstado' => ['label' => 'Estado', 'options' => ['activo' => 'Activos', 'inactivo' => 'Inactivos'], 'value' => $filtroEstado],
        ];
        $activeFilters = array_filter($filterFields, fn ($field) => isset($field['options'][$field['value']]));
    @endphp

    <x-data-table
        id="herramientas"
        label="Tabla de herramientas"
        :columns="\App\Livewire\Inventario\Herramientas\Index::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar herramienta"
        search-placeholder="Nombre, código o marca..."
        default-order-label="orden por nombre"
        :export-disabled="$dateErrors->isNotEmpty()"
        :collapsible-filters="true"
        :active-filter-count="(count($activeFilters)) + (int) ($desde !== '') + (int) ($hasta !== '')"
    >
        <x-slot:export>
            <x-table-export id="herramientas" :action="route('inventario.herramientas.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :show-date-fields="false" :export-filters="['filtroEstado' => $filtroEstado, 'filtroTipo' => $filtroTipo, 'desde' => $desde, 'hasta' => $hasta]" />
        </x-slot:export>
        <x-slot:filters>
            @foreach ($filterFields as $name => $field)
                <x-searchable-select wire:key="herramientas-filter-{{ $name }}" id="herramientas-filter-{{ $name }}"
                    :label="$field['label']" :options="$field['options']" :model="$name"
                    :all-label="$name === 'filtroEstado' ? 'Todas' : 'Todos'" />
            @endforeach
            <x-table-date-filters id="herramientas" :errors="$dateErrors" />
        </x-slot:filters>
        <x-slot:summary>
            Mostrando {{ $herramientas->count() }} de {{ $herramientas->total() }} herramientas encontradas
        </x-slot:summary>

        <x-slot:filterChips>
            <x-table-date-chips id="herramientas" :desde="$desde" :hasta="$hasta" />
            @foreach ($activeFilters as $name => $field)
                @php
                    $chipLabel = $field['label'].': '.$field['options'][$field['value']];
                @endphp
                <button type="button" wire:key="herramientas-filter-chip-{{ $name }}" wire:click="removeFilter('{{ $name }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $chipLabel }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $chipLabel }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($herramientas as $herramienta)

            <tr wire:key="herramientas-row-{{ $herramienta->id }}" wire:click="verHerramienta({{ $herramienta->id }})"
                class="cursor-pointer hover:bg-canvas/60 focus-within:bg-canvas/60">

                @if (in_array('nombre', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-nombre" class="px-5 py-4">

                    <div class="font-medium text-ink">
                        {{ $herramienta->nombre }}
                    </div>

                    @if ($herramienta->marca)

                        <div class="mt-0.5 text-xs text-muted">
                            {{ $herramienta->marca->nombre }}
                        </div>

                    @endif

                </td>
                @endif


                @if (in_array('categoria', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-categoria" class="px-5 py-4 text-gray-600">
                    {{ $herramienta->categoria->nombre }}
                </td>
                @endif


                @if (in_array('tipo_control', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-tipo_control" class="px-5 py-4 text-gray-600">

                    {{ $herramienta->tipo_control === 'individual'
                        ? 'Individual'
                        : 'Por cantidad'
                    }}

                </td>
                @endif


                @if (in_array('codigo_interno', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-codigo_interno" class="px-5 py-4 text-gray-600">
                    {{ $herramienta->codigo_interno ?? '—' }}
                </td>
                @endif


                @if (in_array('cantidad_actual', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-cantidad_actual" class="px-5 py-4">

                    @php
                        $cantidad = (float) $herramienta->cantidad_actual;

                        $nombreUnidad =
                            $cantidad == 1
                                ? $herramienta->unidadMedida->nombre_singular
                                : $herramienta->unidadMedida->nombre_plural;
                    @endphp

                    <span
                        class="{{ $herramienta->tipo_control === 'cantidad'
                            && $cantidad <= (float) $herramienta->stock_minimo
                                ? 'font-semibold text-red-600'
                                : 'text-gray-700'
                        }}"
                    >
                        {{ rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.') }}
                        {{ $nombreUnidad }}
                    </span>


                    @if (
                        $herramienta->tipo_control === 'cantidad'
                        && $cantidad <= (float) $herramienta->stock_minimo
                    )

                        <div class="mt-1 text-xs text-red-600">
                            Stock bajo
                        </div>

                    @endif

                </td>
                @endif


                @if (in_array('disponibilidad', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-disponibilidad" class="px-4 py-3">

                    @if (! $herramienta->activo)

                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            Inactiva
                        </span>

                    @elseif ($herramienta->tipo_control === 'individual')

                        @if ($herramienta->esta_prestada)

                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                Prestada
                            </span>

                        @else

                            <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                Disponible
                            </span>

                        @endif

                    @else

                        @php
                            $prestadas = (float) $herramienta->cantidad_prestada_pendiente;
                            $disponibles = (float) $herramienta->cantidad_disponible_calculada;
                        @endphp

                        <div class="space-y-1">

                            @if ($disponibles > 0)

                                <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                    {{ number_format($disponibles, 2, '.', '') }}
                                    disponibles
                                </span>

                            @else

                                <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                    Sin disponibilidad
                                </span>

                            @endif

                            @if ($prestadas > 0)

                                <div class="text-xs font-medium text-amber-700">
                                    {{ number_format($prestadas, 2, '.', '') }}
                                    prestadas
                                </div>

                            @else

                                <div class="text-xs text-gray-500">
                                    Sin préstamos pendientes
                                </div>

                            @endif

                        </div>

                    @endif

                </td>
                @endif



                @if (in_array('activo', $visibleColumns, true))
                <td wire:key="herramientas-cell-{{ $herramienta->id }}-activo" class="px-5 py-4">

                    @if ($herramienta->activo)

                        <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                            Activa
                        </span>

                    @else

                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                            Inactiva
                        </span>

                    @endif

                </td>
                @endif


                <td class="cursor-default px-4 py-2 text-right">

                    <div class="flex justify-end gap-1">
                        <button type="button" id="ver-herramienta-{{ $herramienta->id }}" wire:click.stop="verHerramienta({{ $herramienta->id }})"
                            aria-haspopup="dialog" class="icon-button" title="Ver herramienta" aria-label="Ver herramienta {{ $herramienta->nombre }}"><x-icon name="eye" /></button>
                        <button type="button" wire:click.stop="editarHerramienta({{ $herramienta->id }})" class="icon-button text-blue-600"
                            title="Editar herramienta" aria-label="Editar herramienta {{ $herramienta->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click.stop="cambiarEstado({{ $herramienta->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $herramienta->activo ? 'desactivar' : 'activar' }} la herramienta {{ $herramienta->nombre }}?"
                            class="icon-button {{ $herramienta->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $herramienta->activo ? 'Desactivar herramienta' : 'Activar herramienta' }}"
                            aria-label="{{ $herramienta->activo ? 'Desactivar' : 'Activar' }} herramienta {{ $herramienta->nombre }}"><x-icon name="power" /></button>
                    </div>

                </td>

            </tr>

        @empty

            <tr>

                <td
                    colspan="{{ count($visibleColumns) + 1 }}"
                    class="px-6 py-12 text-center text-gray-500"
                >
                    No se encontraron herramientas registradas.
                </td>

            </tr>

        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$herramientas" label="Paginación de herramientas" />
        </x-slot:pagination>
    </x-data-table>


    @if ($herramientaDetalle)
        @include('livewire.inventario.herramientas.detalle', ['herramienta' => $herramientaDetalle, 'movimientos' => $movimientosHerramienta])
    @endif

    @if ($mostrarModal)
        @include('livewire.inventario.herramientas.formulario')
    @endif

    @if ($mostrarModalCatalogo)
        @include('livewire.inventario.herramientas.catalogo-rapido')
    @endif
</div>
