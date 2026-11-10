@props(['id', 'action', 'buscar' => '', 'sortColumn' => '', 'sortDirection' => '', 'visibleColumns', 'exportFilters' => []])

<form method="GET" action="{{ $action }}" target="_blank" rel="noopener"
    x-data="{ desde: '', hasta: '' }">
    <p class="text-sm font-semibold">Exportar todas las coincidencias</p>
    <p class="mt-1 mb-3 text-xs text-muted">Respeta filtros, orden y columnas visibles. Se abre en otra pestaña.</p>
    <input type="hidden" name="buscar" value="{{ $buscar }}">
    <input type="hidden" name="sortColumn" value="{{ $sortColumn }}">
    <input type="hidden" name="sortDirection" value="{{ $sortDirection }}">
    @foreach ($exportFilters as $name => $value)
        <input wire:key="{{ $id }}-export-filter-{{ $name }}" type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    @foreach ($visibleColumns as $column)
        <input wire:key="{{ $id }}-export-column-{{ $column }}" type="hidden" name="columns[]" value="{{ $column }}">
    @endforeach
    <fieldset class="space-y-2">
        <legend class="mb-2 text-xs font-medium text-muted">Fecha de registro (opcional)</legend>
        <label class="block text-sm">Desde
            <input type="date" name="desde" x-model="desde" :max="hasta || null" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3">
        </label>
        <label class="block text-sm">Hasta
            <input type="date" name="hasta" x-model="hasta" :min="desde || null" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3">
        </label>
    </fieldset>
    <div class="mt-3 grid gap-1">
        <button name="format" value="xlsx" class="secondary-button justify-start">Excel (.xlsx)</button>
        <button name="format" value="csv" class="secondary-button justify-start">CSV (.csv)</button>
        <button name="format" value="print" class="secondary-button justify-start">Imprimir / Guardar como PDF</button>
    </div>
</form>
