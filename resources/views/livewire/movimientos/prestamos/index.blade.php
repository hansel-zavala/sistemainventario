
<div>
    {{-- Encabezado --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Préstamos de activos
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Control de herramientas y equipos tecnológicos prestados temporalmente.
            </p>
        </div>

        <button
            type="button"
            wire:click="nuevoPrestamo"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo préstamo
        </button>
    </div>

    @if (session()->has('mensaje'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>
    @endif

    {{-- Filtros --}}
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Persona, departamento, código o inventario..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Estado
                </label>
                <select
                    wire:model.live="filtroEstado"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
                    <option value="">Todos</option>
                    <option value="activo">Activos</option>
                    <option value="vencido">Vencidos</option>
                    <option value="completado">Completados</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Historial --}}
    <div class="space-y-4">
        @forelse ($prestamos as $prestamo)
            <div wire:key="prestamo-{{ $prestamo->id }}"
                 class="overflow-hidden rounded-xl bg-white shadow-sm">

                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col justify-between gap-4 md:flex-row">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-800">
                                    Préstamo #{{ $prestamo->id }}
                                </h2>

                                @if ($prestamo->estado === 'completado')
                                    <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                        Completado
                                    </span>
                                @elseif ($prestamo->esta_vencido)
                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        Vencido
                                    </span>
                                @else
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                        Activo
                                    </span>
                                @endif
                            </div>

                            <p class="mt-2 text-sm text-gray-600">
                                <strong>Departamento:</strong>
                                {{ $prestamo->departamentoDestino?->nombre ?? 'No disponible' }}
                            </p>

                            <p class="mt-1 text-sm text-gray-600">
                                <strong>Persona que recibe:</strong>
                                {{ $prestamo->persona_recibe }}
                            </p>
                        </div>

                        <div class="space-y-1 text-sm text-gray-600 md:text-right">
                            <p>
                                <strong>Prestado:</strong>
                                {{ $prestamo->fecha_prestamo->format('d/m/Y h:i A') }}
                            </p>

                            <p>
                                <strong>Devolución prevista:</strong>
                                <span class="{{ $prestamo->esta_vencido ? 'font-semibold text-red-600' : '' }}">
                                    {{ $prestamo->fecha_prevista_devolucion->format('d/m/Y h:i A') }}
                                </span>
                            </p>

                            <p>
                                <strong>Registrado por:</strong>
                                {{ $prestamo->usuario?->name ?? 'No disponible' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Detalles del préstamo --}}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-white text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3 text-left">Tipo</th>
                                <th class="px-6 py-3 text-left">Activo</th>
                                <th class="px-6 py-3 text-left">Código</th>
                                <th class="px-6 py-3 text-left">Estado</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($prestamo->detalles as $detalle)
                                <tr>
                                    <td class="px-6 py-3 text-gray-600">
                                        {{ $detalle->tipo_activo === 'equipo' ? 'Equipo' : 'Herramienta' }}
                                    </td>

                                    <td class="px-6 py-3">
                                        @if ($detalle->tipo_activo === 'equipo')
                                            <div class="font-medium text-gray-800">
                                                {{ $detalle->equipo?->tipoEquipo?->nombre ?? 'Equipo' }}
                                                {{ $detalle->equipo?->marca?->nombre }}
                                                {{ $detalle->equipo?->modelo }}
                                            </div>
                                        @else
                                            <div class="font-medium text-gray-800">
                                                {{ $detalle->herramienta?->nombre ?? 'Herramienta no disponible' }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $detalle->herramienta?->marca?->nombre }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-3 text-gray-600">
                                        @if ($detalle->tipo_activo === 'equipo')
                                            {{ $detalle->equipo?->numero_inventario ?? '—' }}
                                        @else
                                            {{ $detalle->herramienta?->codigo_interno ?? '—' }}
                                        @endif
                                    </td>

                                    <td class="px-6 py-3">
                                        @if ($detalle->estado === 'devuelto')
                                            <span class="font-medium text-green-700">
                                                Devuelto
                                            </span>
                                        @else
                                            <span class="font-medium text-blue-700">
                                                Prestado
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($prestamo->observaciones)
                    <div class="border-t border-gray-100 px-6 py-4 text-sm text-gray-600">
                        <strong>Observaciones:</strong>
                        {{ $prestamo->observaciones }}
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
                No se encontraron préstamos.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $prestamos->links() }}
    </div>

    {{-- Modal nuevo préstamo --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">
            <div class="mx-auto w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Nuevo préstamo de activos
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Registre los activos y la fecha y hora prevista de devolución.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModal"
                        class="text-2xl text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>
                </div>

                <form wire:submit="guardarPrestamo" class="space-y-6">
                    {{-- Datos generales --}}
                    <section>
                        <h3 class="font-semibold text-gray-800">
                            Datos del préstamo
                        </h3>

                        <p class="mb-4 mt-1 text-sm text-gray-500">
                            Indique dónde se utilizarán los activos y quién se responsabiliza de recibirlos.
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Departamento destino *
                                </label>

                                <select
                                    wire:model="departamentoDestinoId"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                >
                                    <option value="">Seleccione...</option>

                                    @foreach ($departamentos as $departamento)
                                        <option value="{{ $departamento->id }}">
                                            {{ $departamento->nombre }}
                                            @if ($departamento->sigla)
                                                ({{ $departamento->sigla }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('departamentoDestinoId')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Persona que recibe *
                                </label>

                                <input
                                    type="text"
                                    wire:model="personaRecibe"
                                    placeholder="Ej. Carlos Martínez"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                >

                                @error('personaRecibe')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Fecha y hora prevista de devolución *
                                </label>

                                <input
                                    type="datetime-local"
                                    wire:model="fechaPrevistaDevolucion"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 md:max-w-sm"
                                >

                                <p class="mt-1 text-xs text-gray-500">
                                    Puede programar la devolución para hoy o para otro día.
                                    Debe ser una hora futura.
                                </p>

                                @error('fechaPrevistaDevolucion')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    {{-- Activos --}}
                    <section class="border-t border-gray-200 pt-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-800">
                                    Activos prestados
                                </h3>

                                <p class="mt-1 text-sm text-gray-500">
                                    Puede agregar herramientas individuales y equipos tecnológicos.
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="agregarActivo"
                                class="inline-flex shrink-0 items-center justify-center rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50"
                            >
                                + Agregar activo
                            </button>
                        </div>

                        @error('activosSeleccionados')
                            <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="space-y-4">
                            @foreach ($activosSeleccionados as $indice => $activo)
                                <div
                                    wire:key="prestamo-activo-{{ $indice }}"
                                    class="rounded-xl border border-gray-200 p-4"
                                >
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="text-sm font-semibold text-gray-700">
                                            Activo {{ $indice + 1 }}
                                        </span>

                                        @if (count($activosSeleccionados) > 1)
                                            <button
                                                type="button"
                                                wire:click="eliminarActivo({{ $indice }})"
                                                class="text-sm font-medium text-red-600 hover:text-red-800"
                                            >
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        {{-- Tipo --}}
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Tipo de activo *
                                            </label>

                                            <select
                                                wire:model.live="activosSeleccionados.{{ $indice }}.tipo"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >
                                                <option value="">Seleccione...</option>
                                                <option value="herramienta">Herramienta individual</option>
                                                <option value="equipo">Equipo tecnológico</option>
                                            </select>

                                            @error("activosSeleccionados.$indice.tipo")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>

                                        {{-- Selección de activo --}}
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Activo *
                                            </label>

                                            <select
                                                wire:key="selector-activo-{{ $indice }}-{{ $activo['tipo'] }}"
                                                wire:model="activosSeleccionados.{{ $indice }}.item_id"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                                @disabled(empty($activo['tipo']))
                                            >
                                                <option value="">
                                                    Seleccione un activo...
                                                </option>

                                                @if ($activo['tipo'] === 'herramienta')
                                                    @foreach ($herramientas as $herramienta)
                                                        <option value="{{ $herramienta->id }}">
                                                            {{ $herramienta->nombre }}
                                                            — {{ $herramienta->codigo_interno }}
                                                            @if ($herramienta->marca)
                                                                — {{ $herramienta->marca->nombre }}
                                                            @endif
                                                        </option>
                                                    @endforeach
                                                @elseif ($activo['tipo'] === 'equipo')
                                                    @foreach ($equipos as $equipo)
                                                        <option value="{{ $equipo->id }}">
                                                            {{ $equipo->tipoEquipo?->nombre }}
                                                            — {{ $equipo->marca?->nombre }}
                                                            {{ $equipo->modelo }}
                                                            — Inv. {{ $equipo->numero_inventario }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>

                                            @error("activosSeleccionados.$indice.item_id")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Observaciones --}}
                    <section>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="3"
                            placeholder="Ej. Activos prestados para una reunión en Catastro."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>

                        @error('observaciones')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </section>

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">
                        <button
                            type="button"
                            wire:click="cerrarModal"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardarPrestamo"
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            Registrar préstamo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
