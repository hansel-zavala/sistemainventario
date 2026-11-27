@props(['id', 'trigger', 'title', 'description', 'label', 'icon', 'badge', 'badgeTone', 'editAction', 'editLabel', 'showTechnicalHistory' => true])

@php
    $pestanas = [
        'general' => 'Información general',
        ...($showTechnicalHistory ? ['tecnico' => 'Historial técnico'] : []),
        'movimientos' => 'Movimientos',
    ];
    $claseEstado = match ($badgeTone) {
        'positive' => 'bg-green-100 text-green-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp

<dialog {{ $attributes }} id="{{ $id }}" wire:ignore.self data-trigger="{{ $trigger }}"
    x-data="{
        tab: 'general',
        moveTab(event, direction) {
            const tabs = [...event.currentTarget.querySelectorAll('[role=tab]')];
            const index = tabs.indexOf(event.target);
            tabs[(index + direction + tabs.length) % tabs.length].focus();
        },
        closeDetails() {
            const dialog = this.$root;
            dialog.close();
            document.getElementById(dialog.dataset.trigger)?.focus({ preventScroll: true });
            this.$wire.cerrarDetalle();
        }
    }"
    x-init="$el.showModal()" x-on:cancel.prevent="closeDetails()" aria-labelledby="{{ $id }}-titulo"
    class="m-auto h-[min(46rem,calc(100dvh-2rem))] w-[calc(100%-2rem)] max-w-[43rem] flex-col overflow-hidden rounded-2xl border border-line bg-surface text-ink shadow-xl backdrop:bg-black/50 open:flex">
    <header class="shrink-0 border-b border-line bg-canvas/50 px-4 pt-4 pb-3 sm:px-5">
        <div class="flex items-start gap-3">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface text-muted">
                <x-icon :name="$icon" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h2 id="{{ $id }}-titulo" class="text-base font-semibold wrap-anywhere sm:text-lg">{{ $title }}</h2>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $claseEstado }}">{{ $badge }}</span>
                </div>
                <p class="mt-1 text-xs wrap-anywhere text-muted">{{ $description }}</p>
            </div>
            <button type="button" autofocus aria-label="Cerrar {{ mb_strtolower($label) }}" x-on:click="closeDetails()"
                class="icon-button rounded-full"><x-icon name="close" class="size-4" /></button>
        </div>

        <div role="tablist" aria-label="{{ $label }}" @class(['mt-3 grid gap-1 sm:gap-2', 'grid-cols-3' => $showTechnicalHistory, 'grid-cols-2' => ! $showTechnicalHistory])
            x-on:keydown.left.prevent="moveTab($event, -1)" x-on:keydown.right.prevent="moveTab($event, 1)"
            x-on:keydown.home.prevent="$refs.general.focus()" x-on:keydown.end.prevent="$refs.movimientos.focus()">
            @foreach ($pestanas as $clave => $etiqueta)
                <button type="button" id="{{ $id }}-tab-{{ $clave }}" role="tab" x-ref="{{ $clave }}"
                    aria-controls="{{ $id }}-panel-{{ $clave }}" x-bind:aria-selected="tab === '{{ $clave }}'"
                    x-bind:tabindex="tab === '{{ $clave }}' ? 0 : -1"
                    x-on:focus="tab = '{{ $clave }}'" x-on:click="tab = '{{ $clave }}'"
                    class="min-h-10 cursor-pointer rounded-full px-2 py-1.5 text-xs font-medium text-muted hover:bg-surface aria-selected:bg-accent-soft aria-selected:text-accent sm:text-sm">
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>
    </header>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div id="{{ $id }}-panel-general" role="tabpanel" aria-labelledby="{{ $id }}-tab-general"
            x-show="tab === 'general'" tabindex="0" class="divide-y divide-line px-4 sm:px-5">
            {{ $general }}
        </div>

        @if ($showTechnicalHistory)
        <div id="{{ $id }}-panel-tecnico" role="tabpanel" aria-labelledby="{{ $id }}-tab-tecnico"
            x-show="tab === 'tecnico'" style="display: none" tabindex="0" class="px-4 py-5 sm:px-5">
            {{ $tecnico }}
        </div>
        @endif

        <div id="{{ $id }}-panel-movimientos" role="tabpanel" aria-labelledby="{{ $id }}-tab-movimientos"
            x-show="tab === 'movimientos'" style="display: none" tabindex="0" class="px-4 py-5 sm:px-5">
            {{ $movimientos }}
        </div>
    </div>

    <footer class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-line bg-canvas/50 px-4 py-3 sm:px-5">
        <button type="button" wire:click="{{ $editAction }}" wire:loading.attr="disabled" wire:target="{{ $editAction }}"
            class="secondary-button rounded-full"><x-icon name="edit" class="size-4" /> {{ $editLabel }}</button>
        <button type="button" x-show="tab !== 'movimientos'" x-on:click="tab = 'movimientos'; $refs.movimientos.focus()"
            class="primary-button rounded-full"><x-icon name="movements" class="size-4" /> Ver movimientos</button>
    </footer>
</dialog>
