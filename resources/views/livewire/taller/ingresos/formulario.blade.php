<div class="mx-auto max-w-5xl space-y-6">
    <header>
        <a href="{{ route('taller.ingresos.index') }}" class="text-sm font-semibold text-accent hover:underline">Volver a ingresos</a>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink">{{ $ingreso?->emitido() ? 'Ingreso '.$ingreso->folio : 'Ingreso del equipo' }}</h1>
            @if ($ingreso)
                <p class="rounded-full border border-line bg-surface px-3 py-1 text-sm font-medium text-ink">
                    {{ $ingreso->estado() }}@if ($ingreso->emitido()) · Revisión {{ $ingreso->revision }} @endif
                </p>
            @endif
        </div>
    </header>

    <nav aria-label="Pasos de Taller" class="rounded-xl border border-line bg-surface px-3 py-4 sm:px-6">
        <ol class="grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-4">
            @foreach (['Ingreso', 'Ficha técnica', 'Diagnóstico', 'Pase de salida'] as $paso)
                <li @if ($loop->first) aria-current="step" @endif class="relative flex items-center gap-2 sm:flex-col sm:text-center">
                    @unless ($loop->last)
                        <span aria-hidden="true" class="absolute top-4 left-[calc(50%+1.5rem)] hidden h-px w-[calc(100%-2.25rem)] bg-line sm:block"></span>
                    @endunless
                    <span aria-hidden="true" @class(['flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-bold', 'border-accent bg-accent text-white' => $loop->first, 'border-line bg-canvas text-muted' => ! $loop->first])>{{ $loop->iteration }}</span>
                    <span @class(['text-sm', 'font-semibold text-accent' => $loop->first, 'text-muted' => ! $loop->first])>{{ $paso }}@unless ($loop->first)<span class="sr-only">, pendiente</span>@endunless</span>
                </li>
            @endforeach
        </ol>
    </nav>

    @if (session('taller_mensaje'))
        <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('taller_mensaje') }}</p>
    @endif
    @error('ingreso')
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $message }}
            @if ($ingreso)
                <a href="{{ route('taller.ingresos.ver', $ingreso) }}" class="font-semibold underline">Recargar ingreso</a>
            @endif
        </div>
    @enderror

    @if ($editable)
        @if ($corrigiendo)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Al guardar cambios en la hoja, imprime la nueva revisión para que la persona la firme.</p>
        @endif
        @if ($errors->has('datos.*'))
            <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Revisa los campos señalados antes de guardar.</p>
        @endif
        <form wire:submit="emitir" class="space-y-5" novalidate>
            @include('livewire.taller.ingresos.campos')

            <div class="flex flex-wrap items-center justify-end gap-3">
                @if ($corrigiendo)
                    <button type="button" wire:click="cancelarCorreccion" class="secondary-button">Cancelar corrección</button>
                @else
                    <button type="button" wire:click="guardarBorrador" wire:loading.attr="disabled" class="secondary-button">Guardar borrador</button>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="primary-button">{{ $corrigiendo ? 'Guardar corrección' : 'Emitir hoja de ingreso' }}</button>
            </div>
        </form>
    @else
        <section aria-labelledby="documento-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
            <h2 id="documento-ingreso" class="text-lg font-semibold text-ink">Hoja de ingreso</h2>
            <dl class="mt-4 grid gap-x-5 gap-y-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Fecha y hora' => $ingreso->fecha_ingreso->format('d/m/Y H:i'),
                    'Departamento' => $ingreso->departamento_nombre,
                    'Entrega' => $ingreso->entregado_por,
                    'Recibe' => $ingreso->tecnico_nombre,
                    'Equipo' => trim($ingreso->tipo_equipo.' '.$ingreso->marca.' '.$ingreso->modelo),
                    'Categoría' => $ingreso->categoria_nombre ?? $ingreso->categoria?->nombre ?? 'Sin registrar',
                    'Número de inventario' => $ingreso->numero_inventario ?? 'Sin número',
                    'Número de serie' => $ingreso->numero_serie ?? 'Sin registrar',
                    'Condición' => $ingreso->condicion_equipo.' · '.$ingreso->estado_fisico,
                    'Permiso para respaldar datos' => $ingreso->autoriza_respaldo ? 'Sí' : 'No',
                ] as $label => $value)
                    <div>
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium wrap-anywhere">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-sm font-medium text-ink">Falla reportada o servicio solicitado</p>
            <p class="mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap text-ink">{{ $ingreso->motivo }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('taller.ingresos.imprimir', $ingreso) }}" class="primary-button">Imprimir hoja</a>
                @if ($ingreso->firmado())
                    <a href="{{ route('taller.ingresos.firmado', $ingreso) }}" class="secondary-button">Descargar hoja firmada</a>
                @else
                    <button type="button" wire:click="corregir" wire:loading.attr="disabled" class="secondary-button">Corregir ingreso</button>
                @endif
            </div>
        </section>

        @unless ($ingreso->firmado())
            <form wire:submit="adjuntarFirma" aria-labelledby="firma-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6" novalidate>
                <h2 id="firma-ingreso" class="text-lg font-semibold text-ink">Adjuntar hoja firmada</h2>
                <p id="tipos-firma" class="mt-1 text-sm text-muted">Sube una foto JPG o PNG, o un PDF de hasta 10 MB.</p>
                <label for="archivo-firmado" class="mt-4 block text-sm font-medium text-ink">Copia firmada</label>
                <input id="archivo-firmado" type="file" wire:model="archivo" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="tipos-firma error-archivo"
                    @if ($errors->has('archivo')) aria-invalid="true" @endif
                    class="mt-1 block w-full text-sm text-ink file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-2 file:font-semibold file:text-accent">
                <p id="error-archivo" class="mt-1 text-sm text-red-700">@error('archivo') {{ $message }} @enderror</p>
                <label class="mt-4 flex items-start gap-2 text-sm text-ink">
                    <input type="checkbox" wire:model="confirmarFirma" aria-describedby="error-confirmar-firma" @if ($errors->has('confirmarFirma')) aria-invalid="true" @endif class="mt-0.5 size-4 accent-accent">
                    La copia contiene la firma y corresponde al folio {{ $ingreso->folio }}, revisión {{ $ingreso->revision }}.
                </label>
                <p id="error-confirmar-firma" class="mt-1 text-sm text-red-700">@error('confirmarFirma') {{ $message }} @enderror</p>
                <button type="submit" wire:loading.attr="disabled" class="primary-button mt-4">Guardar hoja firmada</button>
                <p wire:loading wire:target="archivo" role="status" class="mt-2 text-sm text-muted">Cargando archivo…</p>
            </form>
        @endunless

        @if ($puedeVincular)
            <section aria-labelledby="vincular-equipo" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
                <h2 id="vincular-equipo" class="text-lg font-semibold text-ink">Vincular equipo a Inventario</h2>
                @if ($altaAnterior)
                    @include('livewire.taller.ingresos.alta-ingreso')
                @else
                    <p class="mt-1 text-sm text-muted">Selecciona el mismo equipo si ya tiene una ficha en Inventario o da de alta su ficha desde aquí.</p>
                    <label for="buscar-vinculo" class="mt-4 block text-sm font-medium text-ink">Buscar por número de inventario</label>
                    <input id="buscar-vinculo" type="search" wire:model.live.debounce.300ms="buscarVinculo" maxlength="100" class="profile-input mt-1 max-w-md">
                    @include('livewire.taller.ingresos.resultados-equipo', ['resultados' => $resultadosVinculo, 'consulta' => $buscarVinculo, 'accion' => 'seleccionarVinculo'])
                    @if ($equipoVinculo)
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <p class="text-sm text-ink">Equipo seleccionado <strong>{{ $equipoVinculo->numero_inventario }}</strong></p>
                            <button type="button" wire:click="vincularInventario" wire:loading.attr="disabled" class="secondary-button">Confirmar vínculo</button>
                        </div>
                    @endif
                    <button type="button" wire:click="abrirAlta" wire:loading.attr="disabled" class="secondary-button mt-4">Dar de alta en Inventario</button>
                @endif
                @error('vinculo') <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </section>
        @elseif ($equipoVinculado)
            <p class="rounded-lg border border-line bg-surface px-4 py-3 text-sm text-muted">Vinculado en Inventario al equipo <strong class="text-ink">{{ $equipoVinculado->numero_inventario }}</strong>.</p>
        @endif

        <section aria-labelledby="historial-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
            <h2 id="historial-ingreso" class="mb-4 text-lg font-semibold text-ink">Historial técnico del equipo</h2>
            @include('livewire.taller.ingresos.historial', ['historial' => $historial, 'mensajeVacio' => 'Este equipo aún no tiene otros ingresos de Taller.'])
        </section>
    @endif
    @if ($tipoCatalogo !== '')
        <x-catalogo-rapido-equipo id="catalogo-taller" :tipo="$tipoCatalogo" :categoria="$categoriaCatalogo"
            :opciones="$opcionesCatalogoExistente" :vinculable="$catalogoVinculable" :vincular-existente="$vincularExistente" />
    @endif
</div>
