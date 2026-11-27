<section aria-labelledby="recepcion-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
    <h2 id="recepcion-ingreso" class="text-lg font-semibold text-ink">1. Recepción</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="fecha-ingreso" class="block text-sm font-medium text-ink">Fecha y hora de ingreso</label>
            <input id="fecha-ingreso" type="datetime-local" wire:model="datos.fecha_ingreso" required aria-describedby="error-fecha-ingreso" @if ($errors->has('datos.fecha_ingreso')) aria-invalid="true" @endif class="profile-input mt-1">
            <p id="error-fecha-ingreso" class="mt-1 text-sm text-red-700">@error('datos.fecha_ingreso') {{ $message }} @enderror</p>
        </div>
        <div>
            <label for="departamento-ingreso" class="block text-sm font-medium text-ink">Departamento de procedencia</label>
            <div class="mt-1 flex items-center gap-2">
                <select id="departamento-ingreso" wire:model="datos.departamento_id" required aria-describedby="error-departamento-ingreso" @if ($errors->has('datos.departamento_id')) aria-invalid="true" @endif class="profile-input min-w-0">
                    <option value="">Selecciona un departamento</option>
                    @foreach ($departamentos as $departamento)
                        <option wire:key="departamento-{{ $departamento->id }}" value="{{ $departamento->id }}">{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
                <x-catalogo-rapido-boton tipo="departamento" titulo="Nuevo departamento" />
            </div>
            <p id="error-departamento-ingreso" class="mt-1 text-sm text-red-700">@error('datos.departamento_id') {{ $message }} @enderror</p>
        </div>
        <div>
            <label for="entrega-ingreso" class="block text-sm font-medium text-ink">Nombre de quien entrega</label>
            <input id="entrega-ingreso" type="text" wire:model.blur="datos.entregado_por" maxlength="255" required aria-describedby="error-entrega-ingreso" @if ($errors->has('datos.entregado_por')) aria-invalid="true" @endif class="profile-input mt-1">
            <p id="error-entrega-ingreso" class="mt-1 text-sm text-red-700">@error('datos.entregado_por') {{ $message }} @enderror</p>
        </div>
        <div>
            <label for="tecnico-ingreso" class="block text-sm font-medium text-ink">Técnico que recibe</label>
            <input id="tecnico-ingreso" value="{{ $tecnicoNombre }}" readonly class="profile-input mt-1 bg-canvas">
        </div>
    </div>
</section>

<section aria-labelledby="equipo-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
    <h2 id="equipo-ingreso" class="text-lg font-semibold text-ink">2. Identificación del equipo</h2>
    @if ($equipoId !== null || $equipoTallerId !== null)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-accent-soft px-3 py-2 text-sm text-ink">
            <p>{{ $equipoId !== null ? 'Equipo seleccionado de Inventario' : 'Equipo registrado en Taller' }}</p>
            <button type="button" wire:click="capturarOtroEquipo" wire:loading.attr="disabled" class="secondary-button">Elegir otro equipo</button>
        </div>
    @else
        <div class="mt-4 rounded-lg border border-line bg-canvas p-3 sm:p-4">
            <label for="buscar-equipo-ingreso" class="block text-sm font-medium text-ink">Buscar equipo por número de inventario</label>
            <input id="buscar-equipo-ingreso" type="search" wire:model.live.debounce.300ms="buscarEquipo" maxlength="100" class="profile-input mt-1 max-w-md">
            @include('livewire.taller.ingresos.resultados-equipo', ['resultados' => $resultadosEquipo, 'consulta' => $buscarEquipo, 'accion' => 'seleccionarEquipo'])
            <p class="mt-2 text-sm text-muted">Si es un equipo nuevo para Taller, captura sus datos abajo.</p>

            <div class="mt-4 border-t border-line pt-3">
                <label for="folio-anterior" class="block text-sm font-medium text-ink">Recuperar un equipo de Taller con el folio de otra visita</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    <input id="folio-anterior" type="text" wire:model="folioAnterior" maxlength="30" placeholder="Ej. T-0001" aria-describedby="error-folio-anterior" @if ($errors->has('folioAnterior')) aria-invalid="true" @endif class="profile-input max-w-48">
                    <button type="button" wire:click="recuperarEquipo" wire:loading.attr="disabled" class="secondary-button">Recuperar equipo</button>
                </div>
                <p id="error-folio-anterior" class="mt-1 text-sm text-red-700">@error('folioAnterior') {{ $message }} @enderror</p>
            </div>
        </div>
    @endif

    @if ($equipoId === null && ! $corrigiendo)
        <fieldset class="mt-4 rounded-lg border border-line px-3 pb-3 sm:px-4">
            <legend class="px-1 text-sm font-medium text-ink">Registro del equipo</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1">
                <label class="inline-flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="alta_inventario" value="1" wire:model.live="datos.alta_inventario" class="size-4 accent-accent">
                    Dar de alta en Inventario
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="alta_inventario" value="0" wire:model.live="datos.alta_inventario" class="size-4 accent-accent">
                    Solo Taller
                </label>
            </div>
            @if ($altaEnIngreso)
                <p class="mt-2 text-sm text-muted">El equipo aparecerá en Equipos tecnológicos al emitir la hoja.</p>
            @endif
        </fieldset>
    @endif

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @include('livewire.taller.ingresos.campos-alta', [
            'grupo' => 'datos', 'soloLectura' => $equipoId !== null, 'serieRequerida' => $altaEnIngreso,
            'categoriaRequerida' => ! $corrigiendo || filled($datos['categoria_alta_id'] ?? null),
        ])
        @foreach (['tipo_equipo' => ['Tipo de equipo', 100], 'marca' => ['Marca', 100], 'numero_inventario' => ['Número de inventario', 100], 'modelo' => ['Modelo', 100], 'color' => ['Color', 50]] as $campo => [$etiqueta, $longitud])
            <div wire:key="identificacion-{{ $campo }}-{{ $equipoId ?? 'manual' }}">
                <label for="ingreso-{{ $campo }}" class="block text-sm font-medium text-ink">{{ $etiqueta }}</label>
                @if ($equipoId !== null)
                    <input id="ingreso-{{ $campo }}" type="text" value="{{ $datos[$campo] ?? 'Sin registrar' }}" readonly class="profile-input mt-1 bg-canvas">
                @elseif (isset($opcionesCatalogo[$campo]))
                    @php($nombreGuardado = $catalogoGuardado[$campo] ?? null)
                    @php($sinCategoria = blank($datos['categoria_alta_id'] ?? null))
                    <div class="mt-1 flex items-center gap-2">
                        <select id="ingreso-{{ $campo }}" wire:model="datos.{{ $campo }}"
                            wire:key="catalogo-ingreso-{{ $campo }}-{{ $datos['categoria_alta_id'] ?? 'sin-categoria' }}"
                            @required(! $corrigiendo || ! $sinCategoria || $campo === 'tipo_equipo')
                            @disabled($sinCategoria && blank($nombreGuardado))
                            aria-describedby="error-{{ $campo }}" @if ($errors->has('datos.'.$campo)) aria-invalid="true" @endif class="profile-input min-w-0">
                            <option value="">{{ $sinCategoria ? 'Selecciona una categoría primero' : ($campo === 'tipo_equipo' ? 'Selecciona un tipo de equipo' : 'Selecciona una marca') }}</option>
                            @if (filled($nombreGuardado) && $nombreGuardado === ($datos[$campo] ?? null) && ! in_array($nombreGuardado, $opcionesCatalogo[$campo], true))
                                <option wire:key="{{ $campo }}-guardado" value="{{ $nombreGuardado }}">{{ $nombreGuardado }} (valor guardado)</option>
                            @endif
                            @foreach ($opcionesCatalogo[$campo] as $nombre)
                                <option wire:key="{{ $campo }}-catalogo-{{ $nombre }}" value="{{ $nombre }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <x-catalogo-rapido-boton :tipo="$campo" :titulo="$campo === 'tipo_equipo' ? 'Nuevo tipo de equipo' : 'Nueva marca'" :deshabilitado="$sinCategoria" />
                    </div>
                @else
                    <input id="ingreso-{{ $campo }}" type="text" wire:model="datos.{{ $campo }}" maxlength="{{ $longitud }}" @required($altaEnIngreso)
                        aria-describedby="error-{{ $campo }}" @if ($errors->has('datos.'.$campo)) aria-invalid="true" @endif class="profile-input mt-1">
                @endif
                @if ($equipoId === null)
                    <p id="error-{{ $campo }}" class="mt-1 text-sm text-red-700">@error('datos.'.$campo) {{ $message }} @enderror</p>
                @endif
            </div>
        @endforeach
        <fieldset class="space-y-1" aria-describedby="error-condicion">
            <legend class="text-sm font-medium text-ink">Condición del equipo</legend>
            <div class="flex min-h-12 flex-wrap items-center gap-4">
                @foreach (\App\Models\IngresoTaller::CONDICIONES as $condicion)
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="radio" name="condicion_equipo" value="{{ $condicion }}" wire:model="datos.condicion_equipo" required class="size-4 accent-accent"> {{ $condicion }}
                    </label>
                @endforeach
            </div>
            <p id="error-condicion" class="text-sm text-red-700">@error('datos.condicion_equipo') {{ $message }} @enderror</p>
        </fieldset>
    </div>
</section>

<section aria-labelledby="estado-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
    <h2 id="estado-ingreso" class="text-lg font-semibold text-ink">3. Estado y motivo</h2>
    <div class="mt-4 space-y-4">
        <fieldset class="space-y-2" aria-describedby="error-estado-fisico">
            <legend class="text-sm font-medium text-ink">Estado físico del equipo</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-3">
                @foreach (\App\Models\IngresoTaller::ESTADOS_FISICOS as $estado)
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="radio" name="estado_fisico" value="{{ $estado }}" wire:model="datos.estado_fisico" required class="size-4 accent-accent"> {{ $estado }}
                    </label>
                @endforeach
            </div>
            <p id="error-estado-fisico" class="text-sm text-red-700">@error('datos.estado_fisico') {{ $message }} @enderror</p>
        </fieldset>
        @foreach (['observaciones' => 'Observaciones del equipo', 'motivo' => 'Falla reportada o servicio solicitado'] as $campo => $etiqueta)
            <div>
                <label for="ingreso-{{ $campo }}" class="block text-sm font-medium text-ink">{{ $etiqueta }}</label>
                <textarea id="ingreso-{{ $campo }}" wire:model="datos.{{ $campo }}" rows="3" maxlength="{{ $altaEnIngreso && $campo === 'observaciones' ? 2000 : 3000 }}" @required($campo === 'motivo' || $altaEnIngreso)
                    aria-describedby="error-{{ $campo }}" @if ($errors->has('datos.'.$campo)) aria-invalid="true" @endif class="profile-input mt-1 resize-y"></textarea>
                <p id="error-{{ $campo }}" class="mt-1 text-sm text-red-700">@error('datos.'.$campo) {{ $message }} @enderror</p>
            </div>
        @endforeach
    </div>
</section>

<section aria-labelledby="autorizacion-ingreso" class="rounded-xl border border-line bg-surface p-4 sm:p-6">
    <h2 id="autorizacion-ingreso" class="text-lg font-semibold text-ink">4. Autorización y firma</h2>
    <p class="mt-1 text-sm text-muted">La persona autorizará la revisión al firmar la hoja impresa.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="autoriza-ingreso" class="block text-sm font-medium text-ink">Nombre de quien autoriza la revisión</label>
            <input id="autoriza-ingreso" type="text" wire:model="datos.autorizado_por" maxlength="255" required aria-describedby="error-autoriza-ingreso" @if ($errors->has('datos.autorizado_por')) aria-invalid="true" @endif class="profile-input mt-1">
            <p id="error-autoriza-ingreso" class="mt-1 text-sm text-red-700">@error('datos.autorizado_por') {{ $message }} @enderror</p>
        </div>
        <fieldset class="space-y-1" aria-describedby="error-respaldo">
            <legend class="text-sm font-medium text-ink">¿Autoriza un respaldo de los datos del equipo?</legend>
            <div class="flex min-h-12 flex-wrap items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-ink"><input type="radio" name="autoriza_respaldo" value="1" wire:model="datos.autoriza_respaldo" required class="size-4 accent-accent"> Sí</label>
                <label class="inline-flex items-center gap-2 text-sm text-ink"><input type="radio" name="autoriza_respaldo" value="0" wire:model="datos.autoriza_respaldo" required class="size-4 accent-accent"> No</label>
            </div>
            <p id="error-respaldo" class="text-sm text-red-700">@error('datos.autoriza_respaldo') {{ $message }} @enderror</p>
        </fieldset>
    </div>
</section>
