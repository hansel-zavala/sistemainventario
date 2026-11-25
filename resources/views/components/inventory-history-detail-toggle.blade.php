@props(['controls', 'title', 'openLabel' => 'Ver detalle', 'closeLabel' => 'Ocultar', 'contextPrefix' => 'de la'])

<button type="button" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded"
    aria-controls="{{ $controls }}"
    {{ $attributes->class(['group inline-flex min-h-9 shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-full border border-line bg-surface px-3 text-sm font-medium text-muted hover:bg-accent-soft hover:text-accent']) }}>
    <x-icon name="chevron" class="size-4 rotate-90 group-aria-expanded:-rotate-90" />
    <span class="group-aria-expanded:hidden">{{ $openLabel }}</span>
    <span class="hidden group-aria-expanded:inline">{{ $closeLabel }}</span>
    <span class="sr-only">{{ $contextPrefix }} {{ mb_strtolower($title) }}</span>
</button>
