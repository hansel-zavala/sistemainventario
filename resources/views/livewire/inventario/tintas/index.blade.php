<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Tinta
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Control de botellas de tinta disponibles para impresoras.
            </p>
        </div>


        <button
            type="button"
            wire:click="crearTinta"
            class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
            + Nueva tinta
        </button>

    </div>


    @if (session()->has('mensaje'))

    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
        {{ session('mensaje') }}
    </div>

    @endif


    @php
        $statusOptions = ['activo' => 'Activas', 'inactivo' => 'Inactivas'];
        $activeFilters = array_filter([
            'filtroColor' => $filtroColor === '' ? null : 'Color: '.$filtroColor,
            'filtroEstado' => isset($statusOptions[$filtroEstado]) ? 'Estado: '.$statusOptions[$filtroEstado] : null,
        ]);
    @endphp

    <x-data-table
        id="tintas"
        label="Tabla de tintas"
        :columns="\App\Services\TintasTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar tinta"
        search-placeholder="Referencia, marca, color..."
        default-order-label="orden por nombre"
        :export-disabled="$dateErrors->isNotEmpty()"
        :collapsible-filters="true"
        :active-filter-count="(count($activeFilters)) + (int) ($desde !== '') + (int) ($hasta !== '')"
    >
        <x-slot:export>
            <x-table-export id="tintas" :action="route('inventario.tintas.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :show-date-fields="false" :export-filters="['filtroColor' => $filtroColor, 'filtroEstado' => $filtroEstado, 'desde' => $desde, 'hasta' => $hasta]" />
        </x-slot:export>

        <x-slot:filters>
            <x-searchable-select id="tintas-filter-color" label="Color" :options="$colores->mapWithKeys(fn ($color) => [$color => $color])"
                model="filtroColor" all-label="Todos" class="sm:col-span-2" />
            <x-searchable-select id="tintas-filter-estado" label="Estado" :options="$statusOptions"
                model="filtroEstado" all-label="Todas" class="sm:col-span-2" />
            <x-table-date-filters id="tintas" :errors="$dateErrors" />
        </x-slot:filters>

        <x-slot:summary>
            Mostrando {{ $tintas->count() }} de {{ $tintas->total() }} tintas encontradas
        </x-slot:summary>

        <x-slot:filterChips>
            <x-table-date-chips id="tintas" :desde="$desde" :hasta="$hasta" />
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="tintas-filter-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($tintas as $tinta)
            <tr wire:key="tintas-row-{{ $tinta->id }}" wire:click="verTinta({{ $tinta->id }})"
                class="cursor-pointer hover:bg-canvas/60 focus-within:bg-canvas/60">
                @foreach (\App\Services\TintasTable::COLUMNS as $column => $label)
                    @if (in_array($column, $visibleColumns, true))
                        <td wire:key="tintas-cell-{{ $tinta->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                            @switch($column)
                                @case('nombre')
                                    <div class="font-medium">{{ $tinta->nombre }}</div>
                                    @if ($tinta->marca)
                                        <div class="mt-0.5 text-xs text-muted">{{ $tinta->marca->nombre }}</div>
                                    @endif
                                    @break
                                @case('color')
                                    {{ $tinta->color }}
                                    @break
                                @case('presentacion')
                                    {{ $tinta->presentacion ?? '—' }}
                                    @break
                                @case('existencia')
                                    @php
                                        $equivalente = $tinta->existencia_equivalente;
                                        $stockBajo = $equivalente <= (float) $tinta->stock_minimo_botellas;
                                    @endphp
                                    <div @class(['font-medium', 'text-red-600' => $stockBajo])>
                                        {{ $tinta->botellas_completas }} {{ $tinta->botellas_completas === 1 ? 'botella completa' : 'botellas completas' }}
                                    </div>
                                    @if ((float) $tinta->porcentaje_botella_abierta > 0)
                                        <div class="mt-1 text-sm text-muted">
                                            + {{ rtrim(rtrim(number_format((float) $tinta->porcentaje_botella_abierta, 2, '.', ''), '0'), '.') }} % de una botella abierta
                                        </div>
                                    @endif
                                    <div class="mt-1 text-xs text-muted">Equivalente: {{ number_format($equivalente, 2) }} botellas</div>
                                    @if ($stockBajo)
                                        <div class="mt-1 text-xs font-medium text-red-600">Stock bajo</div>
                                    @endif
                                    @break
                                @case('activo')
                                    <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $tinta->activo, 'bg-red-50 text-red-700' => ! $tinta->activo])>
                                        {{ $tinta->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                    @break
                            @endswitch
                        </td>
                    @endif
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" id="ver-tinta-{{ $tinta->id }}" wire:click.stop="verTinta({{ $tinta->id }})"
                            class="icon-button text-indigo-600" aria-haspopup="dialog" title="Ver tinta"
                            aria-label="Ver tinta {{ $tinta->nombre }}"><x-icon name="eye" /></button>
                        <button type="button" wire:click.stop="editarTinta({{ $tinta->id }})" class="icon-button text-blue-600"
                            title="Editar tinta" aria-label="Editar tinta {{ $tinta->nombre }}"><x-icon name="edit" /></button>
                        <button type="button" wire:click.stop="cambiarEstado({{ $tinta->id }})" wire:loading.attr="disabled"
                            wire:confirm="¿Desea {{ $tinta->activo ? 'desactivar' : 'activar' }} la tinta {{ $tinta->nombre }}?"
                            class="icon-button {{ $tinta->activo ? 'text-red-600' : 'text-green-700' }} disabled:opacity-50"
                            title="{{ $tinta->activo ? 'Desactivar tinta' : 'Activar tinta' }}"
                            aria-label="{{ $tinta->activo ? 'Desactivar' : 'Activar' }} tinta {{ $tinta->nombre }}"><x-icon name="power" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron tintas registradas.</td></tr>
        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$tintas" label="Paginación de tintas" />
        </x-slot:pagination>
    </x-data-table>

    @if ($mostrarModal)
        @include('livewire.inventario.tintas.formulario')
    @endif

    @if ($mostrarModalCatalogo)
        @include('livewire.inventario.catalogo-rapido', [
            'prefijo' => 'tinta',
            'tipoCatalogo' => 'marca',
            'catalogoVinculable' => false,
            'ejemploMarca' => 'Ej. Epson, Canon',
        ])
    @endif

    @if ($tintaDetalle)
        @include('livewire.inventario.tintas.detalle', ['tinta' => $tintaDetalle, 'movimientos' => $movimientosTinta])
    @endif
</div>
