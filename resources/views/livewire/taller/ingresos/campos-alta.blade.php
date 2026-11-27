@php
    $identificacion = $grupo === 'datos' ? $datos : $datosAlta;
    $categoriaId = $identificacion['categoria_alta_id'] ?? null;
    $nombreCategoria = $identificacion['categoria_nombre'] ?? null;
    $soloLectura = $soloLectura ?? false;
@endphp
<div>
    <label for="{{ $grupo }}-categoria-alta" class="block text-sm font-medium text-ink">Categoría</label>
    @if ($soloLectura)
        <input id="{{ $grupo }}-categoria-alta" type="text" value="{{ $nombreCategoria ?? 'Sin registrar' }}" readonly class="profile-input mt-1 bg-canvas">
    @else
        <div class="mt-1 flex items-center gap-2">
            <select id="{{ $grupo }}-categoria-alta" wire:model.live="{{ $grupo }}.categoria_alta_id" @required($categoriaRequerida ?? true)
                aria-describedby="{{ $grupo }}-error-categoria" @if ($errors->has($grupo.'.categoria_alta_id')) aria-invalid="true" @endif class="profile-input min-w-0">
                <option value="">Selecciona una categoría</option>
                @if ($categoriaId && $nombreCategoria && ! $categoriasAlta->contains('id', $categoriaId))
                    <option wire:key="{{ $grupo }}-categoria-guardada" value="{{ $categoriaId }}">{{ $nombreCategoria }} (valor guardado)</option>
                @endif
                @foreach ($categoriasAlta as $categoria)
                    <option wire:key="{{ $grupo }}-categoria-{{ $categoria->id }}" value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                @endforeach
            </select>
            <x-catalogo-rapido-boton tipo="categoria" titulo="Nueva categoría" :grupo="$grupo" />
        </div>
        <p id="{{ $grupo }}-error-categoria" class="mt-1 text-sm text-red-700">@error($grupo.'.categoria_alta_id') {{ $message }} @enderror</p>
    @endif
</div>
<div>
    <label for="{{ $grupo }}-numero-serie" class="block text-sm font-medium text-ink">Número de serie</label>
    @if ($soloLectura)
        <input id="{{ $grupo }}-numero-serie" type="text" value="{{ $identificacion['numero_serie'] ?? 'Sin registrar' }}" readonly class="profile-input mt-1 bg-canvas">
    @else
        <input id="{{ $grupo }}-numero-serie" type="text" wire:model="{{ $grupo }}.numero_serie" maxlength="100" @required($serieRequerida ?? true)
            aria-describedby="{{ $grupo }}-error-serie" @if ($errors->has($grupo.'.numero_serie')) aria-invalid="true" @endif class="profile-input mt-1">
        <p id="{{ $grupo }}-error-serie" class="mt-1 text-sm text-red-700">@error($grupo.'.numero_serie') {{ $message }} @enderror</p>
    @endif
</div>
