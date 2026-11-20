@props(['id', 'label', 'options', 'allLabel', 'model'])

@php
    $choices = [
        ['value' => '', 'label' => $allLabel],
        ...collect($options)->map(fn ($optionLabel, $value) => [
            'value' => (string) $value,
            'label' => $optionLabel,
        ])->values()->all(),
    ];
@endphp

<div {{ $attributes->class(['min-w-0']) }}
    x-data="searchableSelect(@js($model))"
    data-options="{{ json_encode($choices, JSON_THROW_ON_ERROR) }}"
    @click.outside="if (open) close()"
    @focusout="if (open && !$el.contains($event.relatedTarget)) close()">
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-muted">{{ $label }}</label>
    <div x-ref="control" x-resize="controlWidth = $width" class="relative">
        <input id="{{ $id }}" x-ref="input" type="text" role="combobox" autocomplete="off"
            aria-autocomplete="list" aria-controls="{{ $id }}-options" aria-expanded="false"
            :aria-expanded="open" :aria-activedescendant="activeOptionId"
            value="{{ $allLabel }}" :value="inputValue" :placeholder="selectedLabel || 'Buscar o seleccionar'"
            @focus="focused = true" @blur="focused = false" @click="openMenu()"
            @input="search($event.target.value)" @keydown="handleKeydown($event)"
            class="min-h-11 w-full truncate rounded-full border border-line bg-surface py-2 pr-11 pl-4 text-sm text-ink placeholder:text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
        <button type="button" tabindex="-1" aria-label="Mostrar opciones de {{ $label }}"
            aria-controls="{{ $id }}-options" :aria-expanded="open"
            @mousedown.prevent @click="toggleMenu()"
            class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center rounded-full text-neutral-500 hover:text-ink">
            <x-icon name="chevron" class="size-3.5" ::class="open ? '-rotate-90' : 'rotate-90'" />
        </button>
    </div>

    <div x-ref="popup" popover="manual" x-show="open" style="display: none"
        x-anchor.bottom-start.fixed.offset.4="$refs.control"
        :style="{ '--control-width': controlWidth + 'px' }"
        class="fixed inset-auto z-40 m-0 max-h-[min(16rem,40dvh)] w-[min(max(var(--control-width),13rem),calc(100vw-1rem))] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-2 text-sm text-neutral-900 shadow-lg">
        <ul id="{{ $id }}-options" role="listbox" aria-label="{{ $label }}">
            <template x-for="(option, index) in matches" :key="option.value">
                <li :id="optionId(option)" role="option" :aria-selected="option.value === selectedValue"
                    @mousedown.prevent @click="choose(option)"
                    :class="index === activeIndex || option.value === selectedValue ? 'bg-neutral-100' : ''"
                    class="flex min-h-8 cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-1 hover:bg-neutral-100">
                    <span class="min-w-0 wrap-anywhere" x-text="option.label"></span>
                    <x-icon name="check" class="size-3.5" x-show="option.value === selectedValue" />
                </li>
            </template>
        </ul>
        <p x-show="matches.length === 0" aria-hidden="true" class="px-3 py-1 text-muted">Sin resultados</p>
    </div>
    <span role="status" class="sr-only" x-text="open && matches.length === 0 ? 'Sin resultados' : ''"></span>
</div>
