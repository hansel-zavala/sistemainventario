<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Historial de movimientos</h1>
        <p class="mt-1 text-sm text-gray-500">Consulta centralizada de los movimientos realizados en el Departamento de Informática.</p>
    </div>

    @php
        /** @var string $filtroTipo */
        /** @var string $filtroArticuloTipo */
        /** @var string $filtroResponsable */
        /** @var string $orden */

        $columns = \App\Services\HistorialMovimientosTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;
        $selects = [
            'filtroTipo' => ['Movimiento', \App\Services\HistorialMovimientosTable::TYPES],
            'filtroArticuloTipo' => ['Tipo de artículo', \App\Services\HistorialMovimientosTable::ITEMS],
            'filtroResponsable' => ['Responsable', $usuarios],
            'orden' => ['Orden por fecha', $orders],
        ];
        $activeFilters = array_filter([
            'filtroTipo' => isset($selects['filtroTipo'][1][$filtroTipo]) ? 'Movimiento: '.$selects['filtroTipo'][1][$filtroTipo] : null,
            'filtroArticuloTipo' => isset($selects['filtroArticuloTipo'][1][$filtroArticuloTipo]) ? 'Tipo de artículo: '.$selects['filtroArticuloTipo'][1][$filtroArticuloTipo] : null,
            'filtroResponsable' => isset($usuarios[$filtroResponsable]) ? 'Responsable: '.$usuarios[$filtroResponsable] : null,
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
        ]);
        $badgeClasses = [
            'apertura' => 'bg-blue-100 text-blue-800',
            'entrada' => 'bg-green-100 text-green-800',
            'salida' => 'bg-amber-100 text-amber-800',
            'prestamo' => 'bg-purple-100 text-purple-800',
            'devolucion' => 'bg-teal-100 text-teal-800',
            'recarga' => 'bg-indigo-100 text-indigo-800',
        ];
    @endphp

    <x-data-table id="historial-movimientos" label="Registro de movimientos" :columns="$columns"
        :show-columns="false" :sortable="false" :show-actions="false"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar movimientos"
        search-placeholder="Artículo, código, inventario, detalle o responsable...">
        <x-slot:filters>
            @foreach ($selects as $field => [$label, $options])
                <label wire:key="historial-select-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <select wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="historial-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
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
                <label wire:key="historial-date-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="historial-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="historial-movimientos" :action="route('historial.movimientos.exportar')" :buscar="$buscar"
                :visible-columns="array_keys(\App\Services\HistorialMovimientosTable::EXPORT_COLUMNS)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="historial-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $eventosHistorial->count() }} de {{ $eventosHistorial->total() }} movimientos encontrados
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="historial-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($eventosHistorial as $evento)
            <tr wire:key="historial-{{ $evento->fuente }}-{{ $evento->origen_id }}" class="hover:bg-gray-50">
                <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ \Illuminate\Support\Carbon::parse($evento->fecha)->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4">
                    <span @class(['rounded-full px-3 py-1 text-xs font-semibold', $badgeClasses[$evento->tipo] ?? 'bg-gray-100 text-gray-800'])>
                        {{ \App\Services\HistorialMovimientosTable::TYPES[$evento->tipo] ?? $evento->tipo }}
                    </span>
                </td>
                <td class="px-5 py-4">
                    <div class="font-medium text-gray-800">{{ $evento->articulo }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ \App\Services\HistorialMovimientosTable::ITEMS[$evento->tipo_item] ?? 'Artículo' }}</div>
                </td>
                <td class="px-5 py-4">
                    <div class="font-semibold text-gray-800">{{ number_format((float) $evento->cantidad, 2, '.', '') }}</div>
                    <div class="text-xs text-gray-500">{{ $evento->unidad }}</div>
                </td>
                <td class="px-5 py-4 text-gray-600">{{ $evento->responsable ?? 'Usuario no disponible' }}</td>
                <td class="min-w-64 px-5 py-4">
                    @if ($evento->anterior !== null && $evento->posterior !== null)
                        <div class="text-xs text-gray-600">Existencia anterior: {{ number_format((float) $evento->anterior, 2, '.', '') }}</div>
                        <div class="mt-1 text-xs text-gray-600">Existencia posterior: {{ number_format((float) $evento->posterior, 2, '.', '') }}</div>
                    @else
                        <div class="text-xs text-gray-400">Sin saldo de inventario registrado en esta operación.</div>
                    @endif
                    @if ($evento->detalle !== '')
                        <div class="mt-2 text-xs text-gray-500">{{ $evento->detalle }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="px-6 py-12 text-center text-gray-500">
                    {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver los movimientos.' : 'No se encontraron movimientos con los filtros seleccionados.' }}
                </td>
            </tr>
        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$eventosHistorial" label="Paginación del historial de movimientos" />
        </x-slot:pagination>
    </x-data-table>
</div>
