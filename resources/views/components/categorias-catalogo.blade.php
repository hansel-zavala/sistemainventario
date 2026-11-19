@props(['categorias', 'mostrarTipo' => false])

<fieldset>
    <legend class="mb-1 text-sm font-medium text-gray-700">Categorías vinculadas</legend>

    @if ($categorias->isEmpty())
        <p class="text-sm text-gray-600">Cree una categoría para poder vincularla.</p>
    @else
        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-300 p-2">
            @foreach ($categorias as $categoria)
                <label wire:key="categoria-catalogo-{{ $categoria->id }}" class="flex min-h-8 items-center gap-2 px-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="categoriaIds" value="{{ $categoria->id }}" class="rounded border-gray-300">
                    <span>
                        {{ $categoria->nombre }}
                        @if ($mostrarTipo)
                            ({{ \App\Services\CategoriasTable::TYPES[$categoria->aplica_a] }})
                        @endif
                        @unless ($categoria->activo)
                            <span class="text-gray-600">· Inactiva</span>
                        @endunless
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    @error('categoriaIds')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('categoriaIds.*')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</fieldset>
