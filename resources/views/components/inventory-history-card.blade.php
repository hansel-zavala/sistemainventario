@props([
    'id',
    'title',
    'date',
    'user',
    'details',
    'itemLabel',
    'pluralItemLabel',
    'addedLabel',
    'registeredItemsLabel',
    'emptyMessage',
    'userLabel' => 'Registrado por',
    'singleBadge' => null,
    'notes' => null,
    'headerDetailToggle' => false,
    'headerClass' => '',
    'inlineItemLimit' => 1,
    'detailedPreview' => false,
])

@php
    $itemCount = $details->count();
    $hasMultipleItems = $itemCount > 1;
    $showDetailTable = $itemCount > $inlineItemLimit;
    $previewCount = $showDetailTable && $itemCount > 3 ? 2 : $itemCount;
    $preview = $details->take($previewCount);
    $remainingCount = $itemCount - $previewCount;
    $badge = $itemCount === 1 ? ($singleBadge ?? '1 '.mb_strtolower($itemLabel)) : $itemCount.' '.$pluralItemLabel;
    $columns = [
        'before' => ['Anterior', 'text-ink'],
        'added' => [$addedLabel, 'font-medium text-green-700'],
        'after' => ['Posterior', 'font-semibold text-ink'],
    ];
    $showDetailLabel = 'Ver detalle de la '.mb_strtolower($title);
    $hideDetailLabel = 'Ocultar detalle de la '.mb_strtolower($title);
@endphp

<article wire:key="{{ $id }}" aria-labelledby="{{ $id }}-title"
    x-data="{ expanded: false }" class="rounded-xl border border-line bg-canvas/45 px-3 py-4 text-ink sm:px-4">
    <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 {{ $headerClass }}">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="{{ $id }}-title" class="text-sm font-semibold">{{ $title }}</h2>
                @if ($itemCount)
                    <span class="rounded-full bg-line px-2 py-0.5 text-xs font-medium">{{ $badge }}</span>
                @endif
            </div>
            <time datetime="{{ $date->toIso8601String() }}" class="mt-1 block text-xs text-muted tabular-nums">
                {{ $date->format('d/m/Y · H:i') }}
            </time>
            {{ $metadata ?? '' }}
        </div>
        <div @class(['min-w-0', 'ml-auto flex flex-col items-end gap-2' => $headerDetailToggle])>
            <p class="flex min-w-0 items-start gap-1.5 text-xs text-muted">
                <x-icon name="user" class="mt-0.5 size-3.5" />
                <span class="wrap-anywhere"><span class="sr-only">{{ $userLabel }} </span>{{ $user }}</span>
            </p>
            @if ($headerDetailToggle && $itemCount)
                <x-inventory-history-detail-toggle :controls="$id.'-detail'" :title="$title" />
            @endif
        </div>
    </header>

    @if ($itemCount)
        <div @if ($showDetailTable) x-show="!expanded" @endif
            @class(['mt-4', 'border-t border-line pt-3' => $showDetailTable && ! $headerDetailToggle])>
            <div @class(['flex items-end gap-2 sm:items-center' => ! $headerDetailToggle && ! $showDetailTable])>
                @if (! $showDetailTable || $detailedPreview)
                    <div class="min-w-0 flex-1 divide-y divide-line">
                        @foreach ($preview as $detail)
                            <dl wire:key="{{ $id }}-levels-{{ $detail['id'] }}"
                                class="grid grid-cols-3 gap-x-2 gap-y-2.5 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_repeat(3,5rem)]">
                                <div class="col-span-3 min-w-0 sm:col-span-1">
                                    <dt class="text-xs text-muted">{{ $itemLabel }}</dt>
                                    <dd class="mt-0.5 font-medium text-sm wrap-anywhere">
                                        {{ $detail['name'] }}
                                        @if (! empty($detail['color']))
                                            <span class="font-normal text-muted">— {{ $detail['color'] }}</span>
                                        @endif
                                        @isset($detail['inlineSubtitle'])
                                            <span class="block text-xs font-normal text-muted">{{ $detail['inlineSubtitle'] }}</span>
                                        @endisset
                                    </dd>
                                </div>
                                @foreach ($columns as $field => [$label, $style])
                                    <div class="text-center">
                                        <dt class="text-xs text-muted">{{ $label }}</dt>
                                        <dd class="mt-0.5 text-sm tabular-nums wrap-anywhere {{ $style }}">
                                            {{ $detail[$field] }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endforeach
                    </div>
                @else
                    <ul aria-label="Vista previa de {{ $pluralItemLabel }}" class="min-w-0 flex-1 space-y-2">
                        @foreach ($preview as $detail)
                            <li wire:key="{{ $id }}-preview-{{ $detail['id'] }}" class="flex justify-between gap-3 text-sm">
                                <span class="min-w-0 wrap-anywhere">
                                    {{ $detail['name'] }}
                                    @if (! empty($detail['color']))
                                        <span class="text-muted">— {{ $detail['color'] }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 font-medium text-green-700 tabular-nums">
                                    <span class="sr-only">{{ $addedLabel }} </span>{{ $detail['added'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (! $headerDetailToggle && ! $showDetailTable)
                    <button type="button" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded"
                        aria-controls="{{ $id }}-detail"
                        :aria-label="expanded ? @js($hideDetailLabel) : @js($showDetailLabel)"
                        aria-label="{{ $showDetailLabel }}"
                        class="group inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted hover:bg-line hover:text-ink">
                        <x-icon name="chevron" class="size-4 rotate-90 group-aria-expanded:-rotate-90" />
                    </button>
                @endif
            </div>
            @if ($remainingCount > 0)
                <p class="mt-2 text-xs text-muted">Y {{ $remainingCount }} {{ $pluralItemLabel }} más…</p>
            @endif
        </div>
    @else
        <p class="mt-4 text-sm text-muted">{{ $emptyMessage }}</p>
    @endif

    <div id="{{ $id }}-detail" @if ($itemCount) x-show="expanded" style="display: none" @endif>
        @if ($showDetailTable)
            <h3 class="mt-4 text-sm font-semibold">{{ $registeredItemsLabel }}</h3>
            <x-inventory-history-detail-table :id="$id" :title="$title" :details="$details" :item-label="$itemLabel"
                :columns="$columns" class="mt-3" />
        @endif

        @if ($itemCount || $notes)
            <footer class="mt-3 space-y-2 border-t border-line pt-3 text-xs text-muted">
                @if ($hasMultipleItems)
                    <p class="flex items-center gap-1.5">
                        <x-icon name="check" class="size-4 text-green-700" />
                        {{ $itemCount }} {{ mb_strtolower($registeredItemsLabel) }}
                    </p>
                @elseif ($itemCount === 1)
                    <p>Registrado por {{ $user }} el {{ $date->format('d/m/Y') }} a las {{ $date->format('H:i') }}.</p>
                @endif
                @if ($notes)
                    <p class="whitespace-pre-line wrap-anywhere"><span class="font-medium text-ink">Observaciones:</span> {{ $notes }}</p>
                @endif
            </footer>
        @endif
    </div>

    @if ($showDetailTable && ! $headerDetailToggle)
        <button type="button" @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded"
            aria-controls="{{ $id }}-detail"
            class="group mt-3 inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-full border border-line bg-surface px-3 text-sm font-medium text-muted hover:bg-accent-soft hover:text-accent">
            <span class="group-aria-expanded:hidden">Ver registro completo</span>
            <span class="hidden group-aria-expanded:inline">Ocultar detalle</span>
            <x-icon name="chevron" class="size-4 group-aria-expanded:-rotate-90" />
        </button>
    @endif
</article>
