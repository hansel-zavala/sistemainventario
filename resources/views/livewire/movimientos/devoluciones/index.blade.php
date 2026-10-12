
<div>
    {{-- Encabezado --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Devoluciones de activos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registre la devolución de herramientas y equipos tecnológicos.
            </p>
        </div>
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
                    placeholder="Persona, departamento, equipo o herramienta..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Estado del préstamo
                </label>

                <select
                    wire:model.live="filtroEstado"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
                    <option value="pendientes">Pendientes</option>
                    <option value="completados">Completados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Listado --}}
    <div class="space-y-4">
        @forelse ($prestamos as $prestamo)
            @php
                $pendientes = $prestamo->detalles
                    ->where('estado', 'prestado')
                    ->count();

                $devueltos = $prestamo->detalles
                    ->where('estado', 'devuelto')
                    ->count();
            @endphp

            <div
                wire:key="devolucion-prestamo-{{ $prestamo->id }}"
                class="overflow-hidden rounded-xl bg-white shadow-sm"
            >
                {{-- Cabecera del préstamo --}}
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-800">
                                    Préstamo #{{ $prestamo->id }}
                                </h2>

                                @if ($pendientes === 0)
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
                                <strong>Persona que recibió:</strong>
                                {{ $prestamo->persona_recibe }}
                            </p>

                            <p class="mt-1 text-sm text-gray-600">
                                <strong>Pendientes:</strong> {{ $pendientes }}
                                <span class="mx-2">•</span>
                                <strong>Devueltos:</strong> {{ $devueltos }}
                            </p>
                        </div>

                        <div class="space-y-2 text-sm text-gray-600 md:text-right">
                            <p>
                                <strong>Préstamo:</strong>
                                {{ $prestamo->fecha_prestamo->format('d/m/Y h:i A') }}
                            </p>

                            <p>
                                <strong>Devolución prevista:</strong>
                                <span class="{{ $prestamo->esta_vencido ? 'font-semibold text-red-600' : '' }}">
                                    {{ $prestamo->fecha_prevista_devolucion->format('d/m/Y h:i A') }}
                                </span>
                            </p>

                            @if ($pendientes > 0 && $prestamo->estado === 'activo')
                                <button
                                    type="button"
                                    wire:click="abrirDevolucion({{ $prestamo->id }})"
                                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                                >
                                    Registrar devolución
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Activos --}}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3 text-left">Tipo</th>
                                <th class="px-6 py-3 text-left">Activo</th>
                                <th class="px-6 py-3 text-left">Código</th>
                                <th class="px-6 py-3 text-left">Estado</th>
                                <th class="px-6 py-3 text-left">Devolución real</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($prestamo->detalles as $detalle)
                                <tr>
                                    <td class="px-6 py-3 text-gray-600">
                                        {{ $detalle->tipo_activo === 'equipo' ? 'Equipo' : 'Herramienta' }}
                                    </td>

                                    <td class="px-6 py-3">
                                        <div class="font-medium text-gray-800">
                                            @if ($detalle->tipo_activo === 'equipo')
                                                {{ $detalle->equipo?->tipoEquipo?->nombre ?? 'Equipo' }}
                                                {{ $detalle->equipo?->marca?->nombre }}
                                                {{ $detalle->equipo?->modelo }}
                                            @else
                                                {{ $detalle->herramienta?->nombre ?? 'Herramienta no disponible' }}
                                            @endif
                                        </div>
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

                                    <td class="px-6 py-3 text-sm text-gray-600">
                                        @if ($detalle->fecha_devolucion)
                                            {{ $detalle->fecha_devolucion->format('d/m/Y h:i A') }}

                                            <div class="mt-1 text-xs text-gray-500">
                                                Recibido por:
                                                {{ $detalle->usuarioRecibe?->name ?? 'No disponible' }}
                                            </div>

                                            @if ($detalle->observaciones_devolucion)
                                                <div class="mt-1 text-xs text-gray-500">
                                                    {{ $detalle->observaciones_devolucion }}
                                                </div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
                No se encontraron préstamos para el filtro seleccionado.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $prestamos->links() }}
    </div>

    {{-- Modal --}}
    @if ($mostrarModal && $prestamoSeleccionado)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">
            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Registrar devolución
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Préstamo #{{ $prestamoSeleccionado->id }}
                            — {{ $prestamoSeleccionado->persona_recibe }}
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

                <form wire:submit="guardarDevolucion" class="space-y-6">

                    <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        Seleccione únicamente los activos que está recibiendo.
                        La fecha y hora real se registrarán automáticamente al guardar.
                    </div>

                    @error('seleccionados')
                        <p class="text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    <div class="space-y-4">
                        @foreach ($prestamoSeleccionado->detalles as $detalle)
                            <div
                                wire:key="devolver-detalle-{{ $detalle->id }}"
                                class="rounded-xl border border-gray-200 p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <input
                                        id="devolver-{{ $detalle->id }}"
                                        type="checkbox"
                                        wire:model="seleccionados.{{ $detalle->id }}"
                                        class="mt-1 rounded border-gray-300"
                                    >

                                    <div class="min-w-0 flex-1">
                                        <label
                                            for="devolver-{{ $detalle->id }}"
                                            class="block cursor-pointer font-semibold text-gray-800"
                                        >
                                            @if ($detalle->tipo_activo === 'equipo')
                                                {{ $detalle->equipo?->tipoEquipo?->nombre ?? 'Equipo' }}
                                                {{ $detalle->equipo?->marca?->nombre }}
                                                {{ $detalle->equipo?->modelo }}
                                            @else
                                                {{ $detalle->herramienta?->nombre ?? 'Herramienta' }}
                                            @endif
                                        </label>

                                        <p class="mt-1 text-sm text-gray-500">
                                            @if ($detalle->tipo_activo === 'equipo')
                                                Inventario:
                                                {{ $detalle->equipo?->numero_inventario }}
                                            @else
                                                Código:
                                                {{ $detalle->herramienta?->codigo_interno }}
                                            @endif
                                        </p>

                                        <div class="mt-3">
                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Observaciones de devolución
                                            </label>

                                            <textarea
                                                wire:model="observacionesDetalle.{{ $detalle->id }}"
                                                rows="2"
                                                placeholder="Ej. Devuelto completo y en buen estado."
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                            ></textarea>

                                            @error("observacionesDetalle.$detalle->id")
                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
                            wire:target="guardarDevolucion"
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            Confirmar devolución
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
