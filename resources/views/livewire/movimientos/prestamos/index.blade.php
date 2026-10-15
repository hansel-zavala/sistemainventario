<div>
    {{-- Encabezado --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Préstamos de activos
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Préstamos de herramientas individuales, herramientas por cantidad y equipos tecnológicos.
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
                    placeholder="Persona, departamento, herramienta o inventario..."
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
            <div
                wire:key="prestamo-{{ $prestamo->id }}"
                class="overflow-hidden rounded-xl bg-white shadow-sm"
            >
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col justify-between gap-3 md:flex-row">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-800">
                                    Préstamo #{{ $prestamo->id }}
                                </h2>

                                @if ($prestamo->estado === 'completado')
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                                        Completado
                                    </span>
                                @elseif ($prestamo->esta_vencido)
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800">
                                        Vencido
                                    </span>
                                @else
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">
                                        Activo
                                    </span>
                                @endif
                            </div>

                            <p class="mt-2 text-sm text-gray-600">
                                <strong>Destino:</strong>
                                {{ $prestamo->departamentoDestino?->nombre ?? 'No disponible' }}
                            </p>

                            <p class="mt-1 text-sm text-gray-600">
                                <strong>Recibe:</strong>
                                {{ $prestamo->persona_recibe }}
                            </p>
                        </div>

                        <div class="space-y-1 text-sm text-gray-600 md:text-right">
                            <p>
                                <strong>Préstamo:</strong>
                                {{ $prestamo->fecha_prestamo->format('d/m/Y h:i A') }}
                            </p>
                            <p>
                                <strong>Devolución prevista:</strong>
                                {{ $prestamo->fecha_prevista_devolucion->format('d/m/Y h:i A') }}
                            </p>
                            <p>
                                <strong>Registrado por:</strong>
                                {{ $prestamo->usuario?->name ?? 'No disponible' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500">
                                <th class="px-5 py-3 text-left">Tipo</th>
                                <th class="px-5 py-3 text-left">Activo</th>
                                <th class="px-5 py-3 text-left">Código</th>
                                <th class="px-5 py-3 text-right">Prestado</th>
                                <th class="px-5 py-3 text-right">Devuelto</th>
                                <th class="px-5 py-3 text-right">Pendiente</th>
                                <th class="px-5 py-3 text-left">Estado</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($prestamo->detalles as $detalle)
                                @php
                                    $esEquipo = $detalle->equipo_id !== null;
                                    $nombre = $esEquipo
                                        ? trim(
                                            ($detalle->equipo?->tipoEquipo?->nombre ?? 'Equipo')
                                            . ' '
                                            . ($detalle->equipo?->marca?->nombre ?? '')
                                            . ' '
                                            . ($detalle->equipo?->modelo ?? '')
                                        )
                                        : ($detalle->herramienta?->nombre ?? 'Herramienta no disponible');

                                    $codigo = $esEquipo
                                        ? $detalle->equipo?->numero_inventario
                                        : $detalle->herramienta?->codigo_interno;
                                @endphp

                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-600">
                                        @if ($esEquipo)
                                            Equipo
                                        @elseif ($detalle->herramienta?->tipo_control === 'cantidad')
                                            Herramienta por cantidad
                                        @else
                                            Herramienta individual
                                        @endif
                                    </td>

                                    <td class="px-5 py-3 font-medium text-gray-800">
                                        {{ $nombre }}
                                    </td>

                                    <td class="px-5 py-3 text-gray-600">
                                        {{ $codigo ?: '—' }}
                                    </td>

                                    <td class="px-5 py-3 text-right text-gray-600">
                                        {{ number_format((float) $detalle->cantidad_prestada, 2, '.', '') }}
                                    </td>

                                    <td class="px-5 py-3 text-right text-gray-600">
                                        {{ number_format((float) $detalle->cantidad_devuelta, 2, '.', '') }}
                                    </td>

                                    <td class="px-5 py-3 text-right font-semibold text-gray-800">
                                        {{ number_format($detalle->cantidad_pendiente, 2, '.', '') }}
                                    </td>

                                    <td class="px-5 py-3">
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
                    <div class="border-t border-gray-100 px-5 py-3 text-sm text-gray-600">
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

    {{-- Modal --}}
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">
            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Nuevo préstamo de activos
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Registre herramientas y equipos que serán entregados temporalmente.
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
                    <div>
                        <h3 class="font-semibold text-gray-800">
                            Datos del préstamo
                        </h3>

                        <p class="mb-4 mt-1 text-sm text-gray-500">
                            Indique el departamento, responsable y fecha de devolución.
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
                                    placeholder="Nombre de la persona responsable"
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

                                @error('fechaPrevistaDevolucion')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Activos del préstamo --}}
                    <div class="border-t border-gray-200 pt-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-800">
                                    Activos prestados
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Agregue una fila por cada activo diferente.
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
                            <p class="mb-3 text-sm text-red-600">
                                {{ $message }}
                            </p>
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

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
                                                <option value="herramienta_individual">
                                                    Herramienta individual
                                                </option>
                                                <option value="herramienta_cantidad">
                                                    Herramienta por cantidad
                                                </option>
                                                <option value="equipo">
                                                    Equipo tecnológico
                                                </option>
                                            </select>

                                            @error("activosSeleccionados.$indice.tipo")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>

                                        {{-- Artículo --}}
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Activo *
                                            </label>

                                            <select
                                                wire:key="prestamo-selector-{{ $indice }}-{{ $activo['tipo'] }}"
                                                wire:model="activosSeleccionados.{{ $indice }}.item_id"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                                @disabled(empty($activo['tipo']))
                                            >
                                                <option value="">Seleccione...</option>

                                                @if ($activo['tipo'] === 'herramienta_individual')
                                                    @foreach ($herramientasIndividuales as $herramienta)
                                                        <option value="{{ $herramienta->id }}">
                                                            {{ $herramienta->nombre }}
                                                            — {{ $herramienta->codigo_interno }}
                                                        </option>
                                                    @endforeach

                                                @elseif ($activo['tipo'] === 'herramienta_cantidad')
                                                    @foreach ($herramientasCantidad as $herramienta)
                                                        <option value="{{ $herramienta->id }}">
                                                            {{ $herramienta->nombre }}
                                                            — Disponible:
                                                            {{ number_format($disponiblesCantidad[$herramienta->id], 2, '.', '') }}
                                                            {{ $herramienta->unidadMedida?->nombre_plural ?? '' }}
                                                        </option>
                                                    @endforeach

                                                @elseif ($activo['tipo'] === 'equipo')
                                                    @foreach ($equipos as $equipo)
                                                        <option value="{{ $equipo->id }}">
                                                            {{ $equipo->tipoEquipo?->nombre ?? 'Equipo' }}
                                                            — {{ $equipo->marca?->nombre }}
                                                            {{ $equipo->modelo }}
                                                            — {{ $equipo->numero_inventario }}
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

                                        {{-- Cantidad --}}
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Cantidad a prestar *
                                            </label>

                                            @if ($activo['tipo'] === 'herramienta_cantidad')
                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    wire:model="activosSeleccionados.{{ $indice }}.cantidad"
                                                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                                >
                                                <p class="mt-1 text-xs text-gray-500">
                                                    La cantidad disponible se valida al guardar.
                                                </p>
                                            @else
                                                <input
                                                    type="number"
                                                    value="1"
                                                    readonly
                                                    class="w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2 text-gray-600"
                                                >
                                                <p class="mt-1 text-xs text-gray-500">
                                                    Se presta una unidad por registro.
                                                </p>
                                            @endif

                                            @error("activosSeleccionados.$indice.cantidad")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Observaciones --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="3"
                            placeholder="Ej. Herramientas entregadas para trabajos de mantenimiento."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>

                        @error('observaciones')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

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
