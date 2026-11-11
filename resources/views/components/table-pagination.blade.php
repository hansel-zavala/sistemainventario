@props(['paginator', 'label'])

<nav aria-label="{{ $label }}" class="flex flex-wrap items-center justify-between gap-3">
    <span class="text-sm text-muted" aria-live="polite">
        {{ $paginator->total() === 0 ? '0 resultados' : 'Página '.$paginator->currentPage().' de '.$paginator->lastPage() }}
    </span>
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage())
            @unless($paginator->onFirstPage()) wire:loading.attr="disabled" wire:target="previousPage, nextPage" @endunless
            class="secondary-button disabled:cursor-not-allowed disabled:opacity-50">Anterior</button>
        <button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages())
            @if ($paginator->hasMorePages()) wire:loading.attr="disabled" wire:target="previousPage, nextPage" @endif
            class="secondary-button disabled:cursor-not-allowed disabled:opacity-50">Siguiente</button>
    </div>
</nav>
