@php
    $esRegistro = $insumoId === null;
    $claseControl = 'w-full border border-neutral-300 bg-white px-4 py-2 text-sm text-neutral-900 placeholder:text-neutral-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500 aria-invalid:border-red-600';
    $claseError = 'mt-1 text-sm text-red-700';
    $camposCatalogo = [
        ['categoriaId', 'Categoría', $categorias->pluck('nombre', 'id'), 'categoria', 'Nueva categoría'],
        ['marcaId', 'Marca', $marcas->pluck('nombre', 'id'), 'marca', 'Nueva marca'],
        ['unidadMedidaId', 'Unidad de medida', $unidades->mapWithKeys(fn ($unidad) => [
            $unidad->id => $unidad->nombre_singular.($unidad->abreviatura ? ' ('.$unidad->abreviatura.')' : ''),
        ]), 'unidad_medida', 'Nueva unidad de medida'],
    ];
@endphp

<dialog wire:key="modal-insumo" wire:ignore.self x-data x-init="$el.showModal()"
    x-on:cancel.prevent="$wire.cerrarModal()" aria-labelledby="titulo-modal-insumo" aria-describedby="descripcion-modal-insumo"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[43rem] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-4 text-neutral-900 shadow-xl backdrop:bg-black/50 sm:px-5">
    <header class="flex items-start gap-3 border-b border-neutral-200 pb-4">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-neutral-100">
            <x-icon name="box" class="size-4" />
        </span>
        <div class="min-w-0 flex-1">
            <h2 id="titulo-modal-insumo" class="text-lg font-semibold">{{ $esRegistro ? 'Nuevo insumo' : 'Editar insumo' }}</h2>
            <p id="descripcion-modal-insumo" class="mt-0.5 text-xs text-neutral-600">
                {{ $esRegistro ? 'Registra la información del insumo en el inventario.' : 'Actualiza la información del insumo en el inventario.' }}
            </p>
        </div>
        <button type="button" wire:click="cerrarModal" autofocus aria-label="Cerrar"
            class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">
            <x-icon name="close" class="size-4" />
        </button>
    </header>

    @if (session()->has('mensajeCatalogo'))
        <p role="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('mensajeCatalogo') }}</p>
    @endif

    <form wire:submit="guardarInsumo" class="divide-y divide-neutral-200">
        <section aria-labelledby="informacion-insumo" class="py-5">
            <h3 id="informacion-insumo" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="audit" class="size-4 text-neutral-600" /> Información general</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="insumo-nombre" class="mb-1 block text-sm font-medium">Nombre <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input id="insumo-nombre" type="text" wire:model="nombre" required maxlength="150" placeholder="Ej. Conector RJ45"
                        @error('nombre') aria-invalid="true" aria-describedby="insumo-nombre-error" @enderror
                        class="{{ $claseControl }} rounded-full">
                    @error('nombre') <p id="insumo-nombre-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>

                @foreach ($camposCatalogo as [$campo, $etiqueta, $opciones, $catalogo, $accion])
                    @php
                        $esMarca = $catalogo === 'marca';
                        $inhabilitado = $esMarca && ! $categoriaDisponible;
                        $idControl = 'insumo-catalogo-'.$catalogo;
                    @endphp
                    <div wire:key="insumo-campo-{{ $catalogo }}">
                        <label for="{{ $idControl }}" class="mb-1 block text-sm font-medium">
                            {{ $etiqueta }} @unless ($esMarca) <span aria-hidden="true" class="text-red-600">*</span> @endunless
                        </label>
                        <div class="flex items-center gap-1.5">
                            <div class="relative min-w-0 flex-1">
                                <select id="{{ $idControl }}" wire:key="{{ $idControl }}-{{ $esMarca ? $categoriaId : 'opciones' }}"
                                    @if ($catalogo === 'categoria') wire:model.live="{{ $campo }}" @else wire:model="{{ $campo }}" @endif
                                    @required(! $esMarca) @disabled($inhabilitado)
                                    @error($campo) aria-invalid="true" aria-describedby="{{ $idControl }}-error" @enderror
                                    class="{{ $claseControl }} inventory-catalog-select cursor-pointer rounded-full pr-9 invalid:text-neutral-500">
                                    <option value="">{{ $inhabilitado ? 'Seleccione una categoría activa' : ($esMarca ? 'Sin marca' : 'Seleccione una opción') }}</option>
                                    @foreach ($opciones as $valor => $opcion)
                                        <option wire:key="{{ $idControl }}-opcion-{{ $valor }}" value="{{ $valor }}">{{ $opcion }}</option>
                                    @endforeach
                                </select>
                                <x-icon name="chevron" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 rotate-90 text-neutral-500" />
                            </div>
                            <button type="button" wire:click="abrirModalCatalogo('{{ $catalogo }}')" aria-label="{{ $accion }}" title="{{ $accion }}" @disabled($inhabilitado)
                                class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full border border-neutral-300 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-icon name="plus" class="size-4" />
                            </button>
                        </div>
                        @if ($esMarca && $categoriaDisponible && $opciones->isEmpty())
                            <p role="status" class="mt-1 text-xs text-neutral-600">No hay marcas activas vinculadas a esta categoría.</p>
                        @endif
                        @error($campo) <p id="{{ $idControl }}-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="control-insumo" class="py-5">
            <h3 id="control-insumo" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="tag" class="size-4 text-neutral-600" /> Existencias</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div wire:key="insumo-existencia-cantidad">
                    <label for="insumo-cantidad" class="mb-1 block text-sm font-medium">
                        {{ $esRegistro ? 'Cantidad inicial' : 'Cantidad actual' }} @if ($esRegistro) <span aria-hidden="true" class="text-red-600">*</span> @endif
                    </label>
                    <input id="insumo-cantidad" type="number" wire:model="cantidadActual" min="0" step="0.01" @readonly(! $esRegistro) @required($esRegistro)
                        aria-describedby="{{ $esRegistro ? '' : 'insumo-cantidad-ayuda' }}{{ $errors->has('cantidadActual') ? ' insumo-cantidad-error' : '' }}"
                        @error('cantidadActual') aria-invalid="true" @enderror class="{{ $claseControl }} rounded-full read-only:bg-neutral-100 read-only:text-neutral-600">
                    @unless ($esRegistro)
                        <p id="insumo-cantidad-ayuda" class="mt-1 text-xs text-neutral-600">Las existencias se modifican desde Movimientos → Entradas o Salidas.</p>
                    @endunless
                    @error('cantidadActual') <p id="insumo-cantidad-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
                <div wire:key="insumo-stock-minimo">
                    <label for="insumo-stock" class="mb-1 block text-sm font-medium">Stock mínimo <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input id="insumo-stock" type="number" wire:model="stockMinimo" min="0" step="0.01" required
                        aria-describedby="insumo-stock-ayuda{{ $errors->has('stockMinimo') ? ' insumo-stock-error' : '' }}"
                        @error('stockMinimo') aria-invalid="true" @enderror class="{{ $claseControl }} rounded-full">
                    <p id="insumo-stock-ayuda" class="mt-1 text-xs text-neutral-600">Define el nivel a partir del cual se muestra la alerta de stock bajo.</p>
                    @error('stockMinimo') <p id="insumo-stock-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section aria-labelledby="datos-adicionales-insumo" class="space-y-3 py-5">
            <h3 id="datos-adicionales-insumo" class="flex items-center gap-2 text-sm font-semibold"><x-icon name="columns" class="size-4 text-neutral-600" /> Información adicional</h3>
            <div>
                <label for="insumo-observaciones" class="mb-1 block text-sm font-medium">Observaciones</label>
                <textarea id="insumo-observaciones" wire:model="observaciones" maxlength="2000" rows="3" placeholder="Información adicional del insumo..."
                    @error('observaciones') aria-invalid="true" aria-describedby="insumo-observaciones-error" @enderror
                    class="{{ $claseControl }} rounded-2xl"></textarea>
                @error('observaciones') <p id="insumo-observaciones-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="insumo-activo" class="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium">
                    <input id="insumo-activo" type="checkbox" wire:model="activoInsumo" class="size-4 accent-blue-600"
                        @error('activoInsumo') aria-invalid="true" aria-describedby="insumo-activo-error" @enderror>
                    Insumo activo
                </label>
                @error('activoInsumo') <p id="insumo-activo-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
            </div>
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-3 pt-4">
            <p class="text-xs text-neutral-600"><span class="text-red-600" aria-hidden="true">*</span> Campos obligatorios</p>
            <div class="ml-auto flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="cerrarModal"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center rounded-full border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="guardarInsumo"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-icon name="save" class="size-4" /> {{ $esRegistro ? 'Guardar insumo' : 'Guardar cambios' }}
                </button>
            </div>
        </footer>
    </form>
</dialog>
