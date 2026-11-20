@props(['categoria', 'opciones', 'vincularExistente'])

<div class="space-y-3">
    <p class="text-sm text-gray-700">
        @if ($categoria)
            Categoría: <strong>{{ $categoria->nombre }}</strong>
        @else
            Seleccione una categoría activa en el formulario de inventario.
        @endif
    </p>

    <div role="group" aria-label="Acción de catálogo" class="flex flex-wrap gap-2">
        <button
            type="button"
            wire:click="$set('vincularExistente', true)"
            aria-pressed="{{ $vincularExistente ? 'true' : 'false' }}"
            class="cursor-pointer min-h-10 rounded-full border border-gray-300 px-3 text-sm text-gray-700 aria-pressed:border-blue-600 aria-pressed:bg-blue-50 aria-pressed:text-blue-800"
        >
            Vincular existente
        </button>
        <button
            type="button"
            wire:click="$set('vincularExistente', false)"
            aria-pressed="{{ $vincularExistente ? 'false' : 'true' }}"
            class="cursor-pointer min-h-10 rounded-full border border-gray-300 px-3 text-sm text-gray-700 aria-pressed:border-blue-600 aria-pressed:bg-blue-50 aria-pressed:text-blue-800"
        >
            Crear nuevo
        </button>
    </div>

    @if ($vincularExistente)
        <div wire:key="catalogo-rapido-opciones-{{ $categoria?->id }}">
            <label for="catalogo-existente" class="mb-1 block text-sm font-medium text-gray-700">
                Opción del catálogo
            </label>
            <select
                id="catalogo-existente"
                wire:model="catalogoExistenteId"
                @disabled($opciones->isEmpty())
                class="cursor-pointer w-full rounded-full border border-gray-300 px-4 py-2 disabled:bg-gray-100"
            >
                <option value="">Seleccione una opción de equipos existente</option>
                @foreach ($opciones as $opcion)
                    <option wire:key="catalogo-existente-{{ $opcion->id }}" value="{{ $opcion->id }}">
                        {{ $opcion->nombre }}
                    </option>
                @endforeach
            </select>
            @if ($categoria && $opciones->isEmpty())
                <p class="mt-1 text-sm text-gray-600">No hay opciones activas. Puede crear una nueva.</p>
            @endif
            @error('catalogoExistenteId')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
