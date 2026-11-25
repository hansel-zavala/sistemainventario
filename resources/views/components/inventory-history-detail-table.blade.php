@props(['id', 'title', 'details', 'itemLabel', 'columns'])

<table {{ $attributes->class(['w-full table-fixed border-t border-line text-sm']) }}>
    <caption class="sr-only">Detalle de la {{ mb_strtolower($title) }}</caption>
    <thead class="border-b border-line text-xs text-muted">
        <tr>
            <th scope="col" class="py-2 pr-2 text-left font-medium">{{ $itemLabel }}</th>
            @foreach ($columns as [$label])
                <th scope="col" class="w-1/5 py-2 pl-1 text-right font-medium sm:w-24">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody class="divide-y divide-line">
        @foreach ($details as $detail)
            <tr wire:key="{{ $id }}-row-{{ $detail['id'] }}">
                <th scope="row" class="py-2 pr-2 text-left font-medium wrap-anywhere">
                    {{ $detail['name'] }}
                    @if (! empty($detail['subtitle']))
                        <span class="block text-xs font-normal text-muted">{{ $detail['subtitle'] }}</span>
                    @endif
                </th>
                @foreach ($columns as $field => [, $style])
                    <td class="py-2 pl-1 text-right text-xs tabular-nums wrap-anywhere sm:text-sm {{ $style }}">
                        {{ $detail[$field] }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
