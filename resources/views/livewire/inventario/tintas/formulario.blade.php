@php
    $esRegistro = $tintaId === null;
    $claseControl = 'w-full border border-neutral-300 bg-white px-4 py-2 text-sm text-neutral-900 placeholder:text-neutral-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent aria-invalid:border-red-600';
    $claseError = 'mt-1 text-sm text-red-700';
    $camposExistencia = [
        'botellasCompletas' => ['Botellas completas', '1', null, $esRegistro ? 'Registra las botellas completas disponibles inicialmente.' : 'Las botellas completas se modifican desde Movimientos → Entradas o Salidas.'],
        'porcentajeBotellaAbierta' => ['Contenido de botella abierta (%)', '0.01', '99.99', $esRegistro ? 'Indica el porcentaje restante. Usa 0 si no hay una botella abierta.' : 'El porcentaje de la botella abierta se actualiza con las recargas de tinta.'],
        'stockMinimoBotellas' => ['Stock mínimo', '0.01', null, 'Se expresa en botellas equivalentes. Por ejemplo, 1 equivale a una botella.'],
    ];
@endphp

<dialog wire:key="modal-tinta" wire:ignore.self x-data x-init="$el.showModal()"
    x-on:cancel.prevent="$wire.cerrarModal()" aria-labelledby="titulo-modal-tinta" aria-describedby="descripcion-modal-tinta"
    class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[43rem] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-4 text-neutral-900 shadow-xl backdrop:bg-black/50 sm:px-5">
    <header class="flex items-start gap-3 border-b border-neutral-200 pb-4">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-neutral-100"><x-icon name="drop" class="size-4" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="titulo-modal-tinta" class="text-lg font-semibold">{{ $esRegistro ? 'Nueva tinta' : 'Editar tinta' }}</h2>
            <p id="descripcion-modal-tinta" class="mt-0.5 text-xs text-neutral-600">
                {{ $esRegistro ? 'Registra la información de la tinta en el inventario.' : 'Actualiza la información de la tinta en el inventario.' }}
            </p>
        </div>
        <button type="button" wire:click="cerrarModal" autofocus aria-label="Cerrar"
            class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"><x-icon name="close" class="size-4" /></button>
    </header>

    @if (session()->has('mensajeCatalogo'))
        <p role="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('mensajeCatalogo') }}</p>
    @endif

    <form wire:submit="guardarTinta" class="divide-y divide-neutral-200">
        <section aria-labelledby="informacion-tinta" class="py-5">
            <h3 id="informacion-tinta" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="audit" class="size-4 text-neutral-600" /> Información general</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="tinta-nombre" class="mb-1 block text-sm font-medium">Nombre o referencia <span aria-hidden="true" class="text-red-600">*</span></label>
                    <input id="tinta-nombre" type="text" wire:model="nombre" required maxlength="150" placeholder="Ej. Epson 544"
                        @error('nombre') aria-invalid="true" aria-describedby="tinta-nombre-error" @enderror class="{{ $claseControl }} rounded-full">
                    @error('nombre') <p id="tinta-nombre-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tinta-marca" class="mb-1 block text-sm font-medium">Marca</label>
                    <div class="flex items-center gap-1.5">
                        <div class="relative min-w-0 flex-1">
                            <select id="tinta-marca" wire:model="marcaId" class="{{ $claseControl }} inventory-catalog-select cursor-pointer rounded-full pr-9"
                                @error('marcaId') aria-invalid="true" aria-describedby="tinta-marca-error" @enderror>
                                <option value="">Sin marca</option>
                                @foreach ($marcas as $marca)
                                    <option wire:key="tinta-marca-{{ $marca->id }}" value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                                @endforeach
                            </select>
                            <x-icon name="chevron" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 rotate-90 text-neutral-500" />
                        </div>
                        <button type="button" wire:click="abrirModalCatalogo" aria-label="Nueva marca" title="Nueva marca"
                            class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full border border-neutral-300 text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"><x-icon name="plus" class="size-4" /></button>
                    </div>
                    @error('marcaId') <p id="tinta-marca-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tinta-color" class="mb-1 block text-sm font-medium">Color <span aria-hidden="true" class="text-red-600">*</span></label>
                    <div class="relative">
                        <select id="tinta-color" wire:model="color" required class="{{ $claseControl }} inventory-catalog-select cursor-pointer rounded-full pr-9 invalid:text-neutral-500"
                            @error('color') aria-invalid="true" aria-describedby="tinta-color-error" @enderror>
                            <option value="" disabled>Seleccione un color</option>
                            @foreach ($colorOptions as $opcion)
                                <option wire:key="tinta-color-{{ $opcion }}" value="{{ $opcion }}">{{ $opcion }}</option>
                            @endforeach
                        </select>
                        <x-icon name="chevron" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 rotate-90 text-neutral-500" />
                    </div>
                    @error('color') <p id="tinta-color-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tinta-presentacion" class="mb-1 block text-sm font-medium">Presentación</label>
                    <input id="tinta-presentacion" type="text" wire:model="presentacion" maxlength="100" placeholder="Ej. Botella 65 ml"
                        @error('presentacion') aria-invalid="true" aria-describedby="tinta-presentacion-error" @enderror class="{{ $claseControl }} rounded-full">
                    @error('presentacion') <p id="tinta-presentacion-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section aria-labelledby="existencias-tinta" class="py-5">
            <h3 id="existencias-tinta" class="mb-4 flex items-center gap-2 text-sm font-semibold"><x-icon name="tag" class="size-4 text-neutral-600" /> Existencias</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($camposExistencia as $campo => [$etiqueta, $paso, $maximo, $ayuda])
                    @php
                        $idControl = 'tinta-'.\Illuminate\Support\Str::kebab($campo);
                        $soloLectura = ! $esRegistro && $campo !== 'stockMinimoBotellas';
                    @endphp
                    <div wire:key="{{ $idControl }}">
                        <label for="{{ $idControl }}" class="mb-1 block text-sm font-medium">
                            {{ $etiqueta }} @unless ($soloLectura) <span aria-hidden="true" class="text-red-600">*</span> @endunless
                        </label>
                        <input id="{{ $idControl }}" type="number" wire:model="{{ $campo }}" min="0" step="{{ $paso }}"
                            @if ($maximo !== null) max="{{ $maximo }}" @endif @readonly($soloLectura) @required(! $soloLectura)
                            aria-describedby="{{ $idControl }}-ayuda{{ $errors->has($campo) ? ' '.$idControl.'-error' : '' }}"
                            @error($campo) aria-invalid="true" @enderror class="{{ $claseControl }} rounded-full read-only:bg-neutral-100 read-only:text-neutral-600">
                        <p id="{{ $idControl }}-ayuda" class="mt-1 text-xs text-neutral-600">{{ $ayuda }}</p>
                        @error($campo) <p id="{{ $idControl }}-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="datos-adicionales-tinta" class="space-y-3 py-5">
            <h3 id="datos-adicionales-tinta" class="flex items-center gap-2 text-sm font-semibold"><x-icon name="columns" class="size-4 text-neutral-600" /> Información adicional</h3>
            <div>
                <label for="tinta-observaciones" class="mb-1 block text-sm font-medium">Observaciones</label>
                <textarea id="tinta-observaciones" wire:model="observaciones" maxlength="2000" rows="3" placeholder="Información adicional sobre la tinta..."
                    @error('observaciones') aria-invalid="true" aria-describedby="tinta-observaciones-error" @enderror class="{{ $claseControl }} rounded-2xl"></textarea>
                @error('observaciones') <p id="tinta-observaciones-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
            </div>
            <label for="tinta-activa" class="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium">
                <input id="tinta-activa" type="checkbox" wire:model="activoTinta" class="size-4 accent-blue-600"
                    @error('activoTinta') aria-invalid="true" aria-describedby="tinta-activa-error" @enderror>
                Tinta activa
            </label>
            @error('activoTinta') <p id="tinta-activa-error" class="{{ $claseError }}">{{ $message }}</p> @enderror
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-3 pt-4">
            <p class="text-xs text-neutral-600"><span class="text-red-600" aria-hidden="true">*</span> Campos obligatorios</p>
            <div class="ml-auto flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="cerrarModal"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center rounded-full border border-neutral-300 px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="guardarTinta"
                    class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-icon name="save" class="size-4" /> {{ $esRegistro ? 'Guardar tinta' : 'Guardar cambios' }}
                </button>
            </div>
        </footer>
    </form>
</dialog>
