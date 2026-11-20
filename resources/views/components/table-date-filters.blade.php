@props(['id', 'errors'])

@foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
    <label wire:key="{{ $id }}-date-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
        <input type="date" wire:model.live="{{ $field }}" aria-label="{{ $label }} (fecha de registro)"
            @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $id }}-date-error" @endif
            class="cursor-pointer mt-1 min-h-11 w-full rounded-lg border border-line bg-surface hover:bg-surface/50 px-3 text-sm text-ink">
    </label>
@endforeach
@if ($errors->isNotEmpty())
    <p id="{{ $id }}-date-error" role="alert" class="text-sm text-red-700 sm:col-span-2">{{ $errors->first() }}</p>
@endif
