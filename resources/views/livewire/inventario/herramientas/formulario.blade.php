@php
    $esRegistro = $herramientaId === null;
    $porCantidad = $tipoControl === 'cantidad';
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

<dialog wire:key="modal-herramienta" wire:ignore.self x-data x-init="$el.showModal()"
    x-on:cancel.prevent="$wire.cerrarModal()" aria-labelledby="titulo-modal-herramienta" aria-describedby="descripcion-modal-herramienta"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[43rem] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-4 text-neutral-900 shadow-xl backdrop:bg-black/50 sm:px-5">
    <header class="flex items-start gap-3 border-b border-neutral-200 pb-4">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-neutral-100">
            <x-icon name="tools" class="size-4" />
        </span>
        <div class="min-w-0 flex-1">
            <h2 id="titulo-modal-herramienta" class="text-lg font-semibold">{{ $esRegistro ? 'Nueva herramienta' : 'Editar herramienta' }}</h2>
            <p id="descripcion-modal-herramienta" class="mt-0.5 text-xs text-neutral-600">
                {{ $esRegistro ? 'Registra la información de la herramienta en el inventario.' : 'Actualiza la información de la herramienta en el inventario.' }}
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

    <form wire:submit="guardarHerramienta" class="divide-y divide-neutral-200">
        <section aria-labelledby="informacion-herramienta" class="py-5">
            <h3 id="informacion-herramienta" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="audit" class="size-4 text-neutral-600" /> Información general</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="herramienta-nombre" class="mb-1 block text-sm font-medium">Nombre <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input id="herramienta-nombre" type="text" wire:model="nombre" required maxlength="150" placeholder="Ej. Taladro inalámbrico"
                        @error('nombre') aria-invalid="true" aria-describedby="herramienta-nombre-error" @enderror
                        class="{{ $claseControl }} rounded-full">
                    @error('nombre') <p id="herramienta-nombre-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>

                @foreach ($camposCatalogo as [$campo, $etiqueta, $opciones, $catalogo, $accion])
                    @php
                        $esMarca = $catalogo === 'marca';
                        $inhabilitado = $esMarca && ! $categoriaDisponible;
                        $idControl = 'herramienta-catalogo-'.$catalogo;
                    @endphp
                    <div wire:key="herramienta-campo-{{ $catalogo }}">
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

        <section aria-labelledby="control-herramienta" class="py-5">
            <h3 id="control-herramienta" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="tag" class="size-4 text-neutral-600" /> Identificación y existencias</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="herramienta-tipo-control" class="mb-1 block text-sm font-medium">Tipo de control <span aria-hidden="true" class="text-red-600">*</span></label>
                    <div class="relative">
                        <select id="herramienta-tipo-control" wire:model.live="tipoControl" @disabled(! $esRegistro)
                            aria-describedby="herramienta-tipo-control-ayuda{{ $errors->has('tipoControl') ? ' herramienta-tipo-control-error' : '' }}"
                            @error('tipoControl') aria-invalid="true" @enderror class="{{ $claseControl }} inventory-catalog-select cursor-pointer rounded-full pr-9">
                            <option value="individual">Individual</option>
                            <option value="cantidad">Por cantidad</option>
                        </select>
                        <x-icon name="chevron" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 rotate-90 text-neutral-500" />
                    </div>
                    <p id="herramienta-tipo-control-ayuda" class="mt-1 text-xs text-neutral-600">
                        @if (! $esRegistro)
                            El tipo de control no puede modificarse después de registrar la herramienta.
                        @elseif ($porCantidad)
                            Para herramientas iguales controladas por existencias.
                        @else
                            Para herramientas identificadas mediante un código propio.
                        @endif
                    </p>
                    @error('tipoControl') <p id="herramienta-tipo-control-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="herramienta-codigo" class="mb-1 block text-sm font-medium">
                        Código interno @unless ($porCantidad) <span aria-hidden="true" class="text-red-600">*</span> @endunless
                    </label>
                    <input id="herramienta-codigo" type="text" wire:model="codigoInterno" maxlength="100" @required(! $porCantidad) placeholder="Ej. HER-001"
                        @error('codigoInterno') aria-invalid="true" aria-describedby="herramienta-codigo-error" @enderror class="{{ $claseControl }} rounded-full">
                    @error('codigoInterno') <p id="herramienta-codigo-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>

                @if ($porCantidad)
                    <div wire:key="herramienta-existencia-cantidad">
                        <label for="herramienta-cantidad" class="mb-1 block text-sm font-medium">
                            {{ $esRegistro ? 'Cantidad inicial' : 'Cantidad actual' }} @if ($esRegistro) <span aria-hidden="true" class="text-red-600">*</span> @endif
                        </label>
                        <input id="herramienta-cantidad" type="number" wire:model="cantidadActual" min="0" step="0.01" @readonly(! $esRegistro) @required($esRegistro)
                            aria-describedby="{{ $esRegistro ? '' : 'herramienta-cantidad-ayuda' }}{{ $errors->has('cantidadActual') ? ' herramienta-cantidad-error' : '' }}"
                            @error('cantidadActual') aria-invalid="true" @enderror class="{{ $claseControl }} rounded-full read-only:bg-neutral-100 read-only:text-neutral-600">
                        @unless ($esRegistro)
                            <p id="herramienta-cantidad-ayuda" class="mt-1 text-xs text-neutral-600">Las existencias se modifican desde Movimientos → Entradas o Salidas.</p>
                        @endunless
                        @error('cantidadActual') <p id="herramienta-cantidad-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                    </div>
                    <div wire:key="herramienta-stock-minimo">
                        <label for="herramienta-stock" class="mb-1 block text-sm font-medium">Stock mínimo <span aria-hidden="true" class="text-red-600">*</span></label>
                        <input id="herramienta-stock" type="number" wire:model="stockMinimo" min="0" step="0.01" required
                            aria-describedby="herramienta-stock-ayuda{{ $errors->has('stockMinimo') ? ' herramienta-stock-error' : '' }}"
                            @error('stockMinimo') aria-invalid="true" @enderror class="{{ $claseControl }} rounded-full">
                        <p id="herramienta-stock-ayuda" class="mt-1 text-xs text-neutral-600">Define el nivel a partir del cual se muestra la alerta de stock bajo.</p>
                        @error('stockMinimo') <p id="herramienta-stock-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p wire:key="herramienta-existencia-individual" class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-xs text-neutral-600 sm:col-span-2">
                        Una herramienta individual se registra con una unidad de existencia.
                    </p>
                @endif
            </div>
        </section>

        <section aria-labelledby="datos-adicionales-herramienta" class="space-y-3 py-5">
            <h3 id="datos-adicionales-herramienta" class="flex items-center gap-2 text-sm font-semibold"><x-icon name="columns" class="size-4 text-neutral-600" /> Información adicional</h3>
            <div>
                <label for="herramienta-observaciones" class="mb-1 block text-sm font-medium">Observaciones</label>
                <textarea id="herramienta-observaciones" wire:model="observaciones" maxlength="2000" rows="3" placeholder="Detalles o características importantes de la herramienta..."
                    @error('observaciones') aria-invalid="true" aria-describedby="herramienta-observaciones-error" @enderror
                    class="{{ $claseControl }} rounded-2xl"></textarea>
                @error('observaciones') <p id="herramienta-observaciones-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="herramienta-activa" class="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium">
                    <input id="herramienta-activa" type="checkbox" wire:model="activoHerramienta" class="size-4 accent-blue-600"
                        @error('activoHerramienta') aria-invalid="true" aria-describedby="herramienta-activa-error" @enderror>
                    Herramienta activa
                </label>
                @error('activoHerramienta') <p id="herramienta-activa-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
            </div>
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-3 pt-4">
            <p class="text-xs text-neutral-600"><span class="text-red-600" aria-hidden="true">*</span> Campos obligatorios</p>
            <div class="ml-auto flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="cerrarModal"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center rounded-full border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="guardarHerramienta"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-icon name="save" class="size-4" /> {{ $esRegistro ? 'Guardar herramienta' : 'Guardar cambios' }}
                </button>
            </div>
        </footer>
    </form>
</dialog>
