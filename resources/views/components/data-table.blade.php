@props([
    'id',
    'columns',
    'visibleColumns' => null,
    'sortColumn' => '',
    'sortDirection' => '',
    'label' => 'Tabla de datos',
    'searchLabel' => 'Buscar',
    'searchPlaceholder' => 'Buscar...',
    'showSearch' => true,
    'showColumns' => true,
    'sortable' => true,
    'showActions' => true,
    'actionsLabel' => 'Acciones',
])

@php
    $visibleColumns ??= array_keys($columns);
@endphp

<section {{ $attributes->class(['rounded-xl border border-line bg-surface shadow-sm'])->merge(['aria-label' => $label]) }}
    id="{{ $id }}" data-storage-key="{{ $id }}.visibleColumns"
    x-data="{ menu: '' }"
    x-init="
        try {
            const saved = JSON.parse(localStorage.getItem($el.dataset.storageKey));
            if (Array.isArray(saved) && @js($showColumns)) $wire.setVisibleColumns(saved);
        } catch (e) {}
        if (@js($showColumns)) {
            const storageKey = $el.dataset.storageKey;
            $watch('$wire.visibleColumns', value => {
                try { localStorage.setItem(storageKey, JSON.stringify(value)); } catch (e) {}
            });
        }
    "
    @keydown.escape.window="menu = ''">
    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
        @if ($showSearch)
            <div class="relative w-full sm:max-w-sm">
                <label for="{{ $id }}-search" class="sr-only">{{ $searchLabel }}</label>
                <x-icon name="search" class="pointer-events-none absolute top-3 left-3 text-muted" />
                <input id="{{ $id }}-search" type="search" maxlength="200" wire:model.live.debounce.300ms="buscar"
                    placeholder="{{ $searchPlaceholder }}"
                    class="min-h-11 w-full rounded-lg border border-line bg-canvas pr-3 pl-10 text-sm">
            </div>
        @endif
        <div class="flex gap-2">
            @if ($showColumns)
                <div class="relative" @click.outside="if (menu === 'columns') menu = ''">
                    <button type="button" class="secondary-button" @click="menu = menu === 'columns' ? '' : 'columns'"
                        :aria-expanded="menu === 'columns'" aria-controls="{{ $id }}-columns">
                        <x-icon name="columns" /> Columnas
                    </button>
                    <div id="{{ $id }}-columns" x-show="menu === 'columns'" style="display:none"
                        class="absolute right-0 z-20 mt-2 w-60 rounded-lg border border-line bg-surface py-2 shadow-lg">
                        <p class="border-b border-line px-4 pb-2 text-sm font-semibold">Mostrar columnas</p>
                        @foreach ($columns as $column => $label)
                            <label wire:key="{{ $id }}-column-option-{{ $column }}" class="flex min-h-11 cursor-pointer items-center gap-3 px-4 text-sm hover:bg-canvas">
                                <input type="checkbox" wire:click="toggleColumn({{ \Illuminate\Support\Js::from($column) }})"
                                    @checked(in_array($column, $visibleColumns, true))
                                    @disabled(count($visibleColumns) === 1 && in_array($column, $visibleColumns, true))
                                    wire:loading.attr="disabled" class="size-4 accent-blue-600">
                                {{ $label }}
                            </label>
                        @endforeach
                        <p class="px-4 pt-2 text-xs text-muted">Mantenga al menos una columna visible.</p>
                    </div>
                </div>
            @endif
            @isset($export)
                <div class="relative" @click.outside="if (menu === 'export') menu = ''">
                    <button type="button" class="secondary-button" @click="menu = menu === 'export' ? '' : 'export'"
                        :aria-expanded="menu === 'export'" aria-controls="{{ $id }}-export">
                        <x-icon name="download" /> Exportar
                    </button>
                    <div id="{{ $id }}-export" x-show="menu === 'export'" style="display:none"
                        class="absolute right-0 z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-lg border border-line bg-surface p-4 shadow-lg">
                        {{ $export }}
                    </div>
                </div>
            @endisset
        </div>
    </div>
    @isset($filters)
        <div class="flex flex-wrap items-end gap-3 border-b border-line p-4">{{ $filters }}</div>
    @endisset
    <div class="flex items-center justify-between gap-3 px-4 py-2 text-xs text-muted" aria-live="polite">
        <span>{{ $summary ?? '' }}</span>
        <span wire:loading>Actualizando…</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-y border-line bg-canvas text-ink">
                <tr>
                    @foreach ($columns as $column => $label)
                        @if (in_array($column, $visibleColumns, true))
                            <th wire:key="{{ $id }}-heading-{{ $column }}" scope="col" class="px-4 py-2 text-left font-semibold"
                                aria-sort="{{ $sortable && $sortColumn === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                @if ($sortable)
                                    <button type="button" wire:click="sortBy({{ \Illuminate\Support\Js::from($column) }})" class="inline-flex min-h-9 cursor-pointer items-center gap-2 whitespace-nowrap rounded hover:text-accent"
                                        aria-label="Ordenar por {{ $label }}: {{ $sortColumn !== $column ? 'ascendente' : ($sortDirection === 'asc' ? 'descendente' : 'ingresos más recientes') }}">
                                        {{ $label }}
                                        <x-icon :name="$sortColumn === $column ? ($sortDirection === 'asc' ? 'arrow-up' : 'arrow-down') : 'sort'" class="size-4 {{ $sortColumn === $column ? 'text-accent' : 'text-gray-400' }}" />
                                    </button>
                                @else
                                    {{ $label }}
                                @endif
                            </th>
                        @endif
                    @endforeach
                    @if ($showActions)
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ $actionsLabel }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($pagination)
        <div class="border-t border-line px-4 py-4">{{ $pagination }}</div>
    @endisset
</section>
