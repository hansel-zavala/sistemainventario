@php
    $esUnidad = $tipoCatalogo === 'unidad_medida';
    $titulo = match ($tipoCatalogo) {
        'categoria' => 'Nueva categoría',
        'marca' => 'Marca',
        'unidad_medida' => 'Nueva unidad de medida',
        default => 'Nuevo registro',
    };
    $campos = $esUnidad ? [
        ['nuevoNombreCatalogo', 'Nombre en singular', 'Ej. Pieza, Metro, Litro', true],
        ['nuevoNombrePluralUnidad', 'Nombre en plural', 'Ej. Piezas, Metros, Litros', true],
        ['nuevaAbreviaturaUnidad', 'Abreviatura', 'Ej. pza, m, l', false],
    ] : [
        ['nuevoNombreCatalogo', 'Nombre', $tipoCatalogo === 'categoria' ? 'Ej. Manuales, Eléctricas' : 'Ej. Stanley, Bosch', true],
    ];
@endphp

<dialog wire:key="modal-catalogo-herramienta" wire:ignore.self x-data x-init="$el.showModal()"
    x-on:cancel.prevent="$wire.cerrarModalCatalogo()" aria-labelledby="titulo-modal-catalogo-herramienta"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl backdrop:bg-black/50">
    <header class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h3 id="titulo-modal-catalogo-herramienta" class="text-lg font-bold text-gray-800">{{ $titulo }}</h3>
            @unless ($catalogoVinculable)
                <p class="text-xs text-gray-500">{{ $esUnidad ? 'Indica los nombres y si la unidad admite cantidades decimales.' : 'Ingrese el nombre para registrarlo rápidamente.' }}</p>
            @endunless
        </div>
        <button type="button" wire:click="cerrarModalCatalogo" autofocus aria-label="Cerrar catálogo rápido"
            class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">
            <x-icon name="close" class="size-4" />
        </button>
    </header>

    <form wire:submit="guardarCatalogoRapido" class="space-y-4">
        @if ($catalogoVinculable)
            <x-catalogo-rapido-vinculo :categoria="$categoriaCatalogo" :opciones="$opcionesCatalogoExistente"
                :vincular-existente="$vincularExistente" placeholder="Seleccione una marca existente" />
        @endif

        @unless ($catalogoVinculable && $vincularExistente)
            @foreach ($campos as [$campo, $etiqueta, $ejemplo, $obligatorio])
                @php($idControl = 'herramienta-catalogo-rapido-'.$campo)
                <div wire:key="campo-{{ $idControl }}">
                    <label for="{{ $idControl }}" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ $etiqueta }} @if ($obligatorio) <span aria-hidden="true" class="text-red-600">*</span> @endif
                    </label>
                    <input id="{{ $idControl }}" type="text" wire:model="{{ $campo }}" placeholder="{{ $ejemplo }}" @required($obligatorio)
                        @error($campo) aria-invalid="true" aria-describedby="{{ $idControl }}-error" @enderror
                        class="w-full rounded-full border border-gray-300 px-4 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent aria-invalid:border-red-600">
                    @error($campo) <p id="{{ $idControl }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            @if ($esUnidad)
                <label for="herramienta-catalogo-rapido-decimales" class="flex items-center gap-2 text-sm text-gray-700">
                    <input id="herramienta-catalogo-rapido-decimales" type="checkbox" wire:model="nuevoPermiteDecimalesUnidad" class="size-4 accent-blue-600">
                    Permite cantidades decimales
                </label>
            @endif
        @endunless

        <footer class="flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-4">
            <button type="button" wire:click="cerrarModalCatalogo"
                class="cursor-pointer rounded-full border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">Cancelar</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="guardarCatalogoRapido"
                class="cursor-pointer rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                {{ $catalogoVinculable && $vincularExistente ? 'Vincular y seleccionar' : 'Guardar' }}
            </button>
        </footer>
    </form>
</dialog>
