@props(['id', 'desde', 'hasta'])

@foreach (['desde' => ['Desde', $desde], 'hasta' => ['Hasta', $hasta]] as $field => [$label, $value])
    @continue($value === '')
    <button type="button" wire:key="{{ $id }}-date-chip-{{ $field }}" wire:click="removeDateFilter('{{ $field }}')"
        wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}: {{ $value }}"
        class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
        <span class="min-w-0 wrap-break-word">{{ $label }}: {{ $value }}</span>
        <x-icon name="close" class="size-3.5" />
    </button>
@endforeach
