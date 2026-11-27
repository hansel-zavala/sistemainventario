@props(['tipo', 'titulo', 'grupo' => 'datos', 'deshabilitado' => false])

<button type="button" wire:click="abrirModalCatalogo('{{ $tipo }}', '{{ $grupo }}')" wire:loading.attr="disabled"
    aria-label="{{ $titulo }}" title="{{ $titulo }}" @disabled($deshabilitado)
    class="inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-line bg-surface text-accent hover:bg-accent-soft disabled:cursor-not-allowed disabled:opacity-50">
    <x-icon name="plus" class="size-4" />
</button>
