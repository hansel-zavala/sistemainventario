@props(['id', 'tipo', 'categoria', 'opciones', 'vinculable', 'vincularExistente'])

<dialog wire:key="{{ $id }}" wire:ignore.self x-data x-init="$el.showModal()" x-on:cancel.prevent="$wire.cerrarModalCatalogo()"
    aria-labelledby="{{ $id }}-titulo"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl bg-surface p-6 shadow-xl backdrop:bg-black/50">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="{{ $id }}-titulo" class="text-lg font-bold text-ink">{{ match ($tipo) {
            'categoria' => 'Nueva categoría',
            'tipo_equipo' => 'Tipo de equipo',
            'departamento' => 'Nuevo departamento',
            'marca' => 'Marca',
            default => 'Registro de catálogo',
        } }}</h2>
        <button type="button" wire:click="cerrarModalCatalogo" aria-label="Cerrar" class="icon-button"><x-icon name="close" class="size-4" /></button>
    </div>

    <form wire:submit="guardarCatalogoRapido" class="space-y-4" novalidate>
        @if ($vinculable)
            <x-catalogo-rapido-vinculo :categoria="$categoria" :opciones="$opciones" :vincular-existente="$vincularExistente" />
        @endif
        @unless ($vinculable && $vincularExistente)
            <div>
                <label for="{{ $id }}-nombre" class="block text-sm font-medium text-ink">Nombre</label>
                <input id="{{ $id }}-nombre" type="text" wire:model="nuevoNombreCatalogo" required maxlength="{{ $tipo === 'departamento' ? 150 : 100 }}"
                    @if ($errors->has('nuevoNombreCatalogo')) aria-invalid="true" aria-describedby="{{ $id }}-error-nombre" @endif class="profile-input mt-1" autofocus>
                @error('nuevoNombreCatalogo') <p id="{{ $id }}-error-nombre" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            @if ($tipo === 'departamento')
                <div>
                    <label for="{{ $id }}-sigla" class="block text-sm font-medium text-ink">Sigla (opcional)</label>
                    <input id="{{ $id }}-sigla" type="text" wire:model="nuevaSiglaDepartamento" maxlength="20" placeholder="Ej. RRHH"
                        @if ($errors->has('nuevaSiglaDepartamento')) aria-invalid="true" aria-describedby="{{ $id }}-error-sigla" @endif class="profile-input mt-1">
                    @error('nuevaSiglaDepartamento') <p id="{{ $id }}-error-sigla" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endif
        @endunless
        <div class="flex flex-wrap justify-end gap-2 border-t border-line pt-4">
            <button type="button" wire:click="cerrarModalCatalogo" class="secondary-button">Cancelar</button>
            <button type="submit" wire:loading.attr="disabled" class="primary-button">{{ $vinculable && $vincularExistente ? 'Vincular y seleccionar' : 'Guardar' }}</button>
        </div>
    </form>
</dialog>
