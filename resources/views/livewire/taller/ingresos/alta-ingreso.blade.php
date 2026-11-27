<form wire:submit="darDeAlta" class="mt-4 space-y-4" novalidate>
    <p class="text-sm text-muted">Revisa los datos que se guardarán en la ficha de Inventario.</p>
    @if ($errors->has('datosAlta.*'))
        <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Revisa los campos señalados para dar de alta el equipo.</p>
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        @include('livewire.taller.ingresos.campos-alta', ['grupo' => 'datosAlta'])
        @foreach (['tipo_equipo' => 'Tipo de equipo', 'marca' => 'Marca'] as $campo => $etiqueta)
            <div>
                <label for="alta-{{ $campo }}" class="block text-sm font-medium text-ink">{{ $etiqueta }}</label>
                @php($sinCategoria = blank($datosAlta['categoria_alta_id'] ?? null))
                <div class="mt-1 flex items-center gap-2">
                    <select id="alta-{{ $campo }}" wire:model="datosAlta.{{ $campo }}" required wire:key="alta-{{ $campo }}-{{ $datosAlta['categoria_alta_id'] ?? 'sin-categoria' }}"
                        @disabled($sinCategoria) aria-describedby="alta-error-{{ $campo }}" @if ($errors->has('datosAlta.'.$campo)) aria-invalid="true" @endif class="profile-input min-w-0">
                        <option value="">{{ $sinCategoria ? 'Selecciona una categoría primero' : 'Selecciona una opción' }}</option>
                        @foreach ($opcionesAlta[$campo] as $nombre)
                            <option wire:key="alta-{{ $campo }}-{{ $nombre }}" value="{{ $nombre }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <x-catalogo-rapido-boton :tipo="$campo" grupo="datosAlta"
                        :titulo="$campo === 'tipo_equipo' ? 'Nuevo tipo de equipo' : 'Nueva marca'" :deshabilitado="$sinCategoria" />
                </div>
                @if (filled($ingreso->{$campo}) && ($datosAlta[$campo] ?? null) !== $ingreso->{$campo})
                    <p class="mt-1 text-sm text-muted">En la hoja de ingreso aparece {{ $ingreso->{$campo} }}.</p>
                @endif
                <p id="alta-error-{{ $campo }}" class="mt-1 text-sm text-red-700">@error('datosAlta.'.$campo) {{ $message }} @enderror</p>
            </div>
        @endforeach
        <div>
            <label for="alta-departamento" class="block text-sm font-medium text-ink">Departamento de procedencia</label>
            <div class="mt-1 flex items-center gap-2">
                <select id="alta-departamento" wire:model="datosAlta.departamento_id" required aria-describedby="alta-error-departamento"
                    @if ($errors->has('datosAlta.departamento_id')) aria-invalid="true" @endif class="profile-input min-w-0">
                    <option value="">Selecciona un departamento</option>
                    @foreach ($departamentos as $departamento)
                        <option wire:key="alta-departamento-{{ $departamento->id }}" value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
                <x-catalogo-rapido-boton tipo="departamento" titulo="Nuevo departamento" grupo="datosAlta" />
            </div>
            <p id="alta-error-departamento" class="mt-1 text-sm text-red-700">@error('datosAlta.departamento_id') {{ $message }} @enderror</p>
        </div>
        @foreach (['numero_inventario' => ['Número de inventario', 100], 'modelo' => ['Modelo', 100], 'color' => ['Color', 50]] as $campo => [$etiqueta, $longitud])
            <div wire:key="alta-campo-{{ $campo }}">
                <label for="alta-{{ $campo }}" class="block text-sm font-medium text-ink">{{ $etiqueta }}</label>
                <input id="alta-{{ $campo }}" type="text" wire:model="datosAlta.{{ $campo }}" maxlength="{{ $longitud }}" required
                    aria-describedby="alta-error-{{ $campo }}" @if ($errors->has('datosAlta.'.$campo)) aria-invalid="true" @endif class="profile-input mt-1">
                <p id="alta-error-{{ $campo }}" class="mt-1 text-sm text-red-700">@error('datosAlta.'.$campo) {{ $message }} @enderror</p>
            </div>
        @endforeach
        <div class="sm:col-span-2">
            <label for="alta-observaciones" class="block text-sm font-medium text-ink">Observaciones</label>
            <textarea id="alta-observaciones" wire:model="datosAlta.observaciones" rows="3" maxlength="2000" required aria-describedby="alta-error-observaciones"
                @if ($errors->has('datosAlta.observaciones')) aria-invalid="true" @endif class="profile-input mt-1 resize-y"></textarea>
            <p id="alta-error-observaciones" class="mt-1 text-sm text-red-700">@error('datosAlta.observaciones') {{ $message }} @enderror</p>
        </div>
    </div>
    <div class="flex flex-wrap justify-end gap-2">
        <button type="button" wire:click="cancelarAlta" wire:loading.attr="disabled" class="secondary-button">Cancelar</button>
        <button type="submit" wire:loading.attr="disabled" class="primary-button">Registrar equipo en Inventario</button>
    </div>
</form>
