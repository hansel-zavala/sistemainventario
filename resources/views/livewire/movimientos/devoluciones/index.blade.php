
<div>
    {{-- Encabezado --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            Devoluciones de activos
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Controle devoluciones parciales y completas.
        </p>
    </div>

    @if (session()->has('mensaje'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>
    @endif

    @php
        /** @var string $filtroEstado */
        /** @var string $orden */
        /** @var string $tipoArticulo */
        /** @var string $destino */
        /** @var string $tipoFecha */

        $types = \App\Services\PrestamosTable::TYPES;
        $columns = \App\Services\DevolucionesTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;

        $states = \App\Services\DevolucionesTable::STATES;
        $dateTypes = \App\Services\DevolucionesTable::DATE_TYPES;
        $activeFilters = array_filter([
            'tipoFecha' => $tipoFecha !== \App\Services\DevolucionesTable::DEFAULT_DATE && isset($dateTypes[$tipoFecha]) ? $dateTypes[$tipoFecha] : null,
            'filtroEstado' => $filtroEstado !== \App\Services\DevolucionesTable::DEFAULT_STATE && isset($states[$filtroEstado]) ? 'Estado: '.$states[$filtroEstado] : null,
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'tipoArticulo' => isset($types[$tipoArticulo]) ? 'Tipo de activo: '.$types[$tipoArticulo] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
            'destino' => isset($destinationOptions[$destino]) ? 'Departamento destino: '.$destinationOptions[$destino] : null,
        ]);
    @endphp

    <x-data-table id="devoluciones" label="Listado de devoluciones" :columns="$columns"
        :show-columns="false" :sortable="false" :show-actions="false"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar devoluciones"
        search-placeholder="Activo, código, responsable o departamento...">
        <x-slot:filters>
            <label class="text-sm font-medium text-muted sm:col-span-1">Estado
                <select wire:model.live="filtroEstado" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    @foreach ($states as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-1">Tipo de activo
                <select wire:model.live="tipoArticulo" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-1">Departamento destino
                <select wire:model.live="destino" @if ($filterErrors->has('destino')) aria-invalid="true" aria-describedby="devoluciones-filter-error" @endif
                    class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    @foreach ($destinationOptions as $value => $label)
                        <option wire:key="devoluciones-destino-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-1">Fecha de referencia
                <select wire:model.live="tipoFecha" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    @foreach ($dateTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-2">Orden por fecha
                <select wire:model.live="orden" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    @foreach ($orders as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
                <label wire:key="devoluciones-filter-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="devoluciones-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="devoluciones" :action="route('movimientos.devoluciones.exportar')" :buscar="$buscar"
                :visible-columns="array_keys($columns)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="devoluciones-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $prestamos->count() }} de {{ $prestamos->total() }} préstamos encontrados
                @if ($filtroEstado === \App\Services\DevolucionesTable::DEFAULT_STATE)
                    · Pendientes
                @endif
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="devoluciones-filter-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>
        <x-slot:content>
    <div class="space-y-4 p-4">
        @forelse ($prestamos as $prestamo)
            <div
                wire:key="devolucion-{{ $prestamo->id }}"
                class="overflow-hidden rounded-xl bg-white shadow-sm"
            >
                <div class="flex flex-col justify-between gap-4 border-b border-gray-200 bg-gray-50 px-5 py-4 md:flex-row">
                    <div>
                        <h2 class="font-semibold text-gray-800">
                            Préstamo #{{ $prestamo->id }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            <strong>Departamento:</strong>
                            {{ $prestamo->departamentoDestino?->nombre ?? 'No disponible' }}
                        </p>

                        <p class="mt-1 text-sm text-gray-600">
                            <strong>Responsable:</strong>
                            {{ $prestamo->persona_recibe }}
                        </p>

                        <p class="mt-1 text-sm text-gray-600">
                            <strong>Fecha prevista:</strong>
                            {{ $prestamo->fecha_prevista_devolucion->format('d/m/Y h:i A') }}
                        </p>
                    </div>

                    <div class="flex flex-col items-start gap-2 md:items-end">
                        @if ($prestamo->estado === 'completado')
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                Completado
                            </span>
                        @elseif ($prestamo->esta_vencido)
                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                                Vencido
                            </span>
                        @else
                            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                Activo
                            </span>
                        @endif

                        @if ($prestamo->estado === 'activo')
                            <button
                                type="button"
                                wire:click="abrirDevolucion({{ $prestamo->id }})"
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            >
                                Registrar devolución
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-xs uppercase text-gray-500">
                                <th class="px-5 py-3 text-left">Activo</th>
                                <th class="px-5 py-3 text-right">Prestado</th>
                                <th class="px-5 py-3 text-right">Devuelto</th>
                                <th class="px-5 py-3 text-right">Pendiente</th>
                                <th class="px-5 py-3 text-left">Estado</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($prestamo->detalles as $detalle)
                                @php
                                    $asset = \App\Services\PrestamosTable::asset($detalle);
                                @endphp
                                <tr wire:key="devoluciones-activo-{{ $detalle->id }}">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-800">
                                            {{ $asset['articulo'] }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $asset['codigo'] ?: 'Control por cantidad' }}
                                        </div>
                                    </td>

                                    <td class="px-5 py-3 text-right">
                                        {{ number_format((float) $detalle->cantidad_prestada, 2) }}
                                    </td>

                                    <td class="px-5 py-3 text-right">
                                        {{ number_format((float) $detalle->cantidad_devuelta, 2) }}
                                    </td>

                                    <td class="px-5 py-3 text-right font-semibold">
                                        {{ number_format($detalle->cantidad_pendiente, 2) }}
                                    </td>

                                    <td class="px-5 py-3">
                                        @if ($detalle->estado === 'devuelto')
                                            <span class="text-sm font-medium text-green-700">
                                                Devuelto
                                            </span>
                                        @else
                                            <span class="text-sm font-medium text-blue-700">
                                                Prestado
                                            </span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($detalle->devoluciones->isNotEmpty())
                                    <tr wire:key="devoluciones-historial-{{ $detalle->id }}">
                                        <td colspan="5" class="bg-gray-50 px-5 py-3">
                                            <p class="mb-2 text-xs font-semibold uppercase text-gray-500">
                                                Historial de devoluciones
                                            </p>

                                            <div class="space-y-2">
                                                @foreach ($detalle->devoluciones as $devolucion)
                                                    <div wire:key="devoluciones-entrega-{{ $devolucion->id }}" class="text-sm text-gray-600">
                                                        <strong>
                                                            {{ number_format((float) $devolucion->cantidad, 2) }}
                                                        </strong>
                                                        unidades devueltas el
                                                        {{ $devolucion->fecha_devolucion->format('d/m/Y h:i A') }}
                                                        por
                                                        {{ $devolucion->usuario?->name ?? 'Usuario no disponible' }}

                                                        @if ($devolucion->observaciones)
                                                            <div class="text-xs text-gray-500">
                                                                {{ $devolucion->observaciones }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
                {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver los préstamos.' : 'No se encontraron préstamos.' }}
            </div>
        @endforelse
    </div>

        </x-slot:content>
        <x-slot:pagination>
            <x-table-pagination :paginator="$prestamos" label="Paginación de devoluciones" />
        </x-slot:pagination>
    </x-data-table>

    {{-- Modal de devolución --}}
    @if ($mostrarModal && $prestamoSeleccionado)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">
            <div class="mx-auto w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between gap-3">
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

                <form wire:submit="guardarDevolucion" class="space-y-5">
                    <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                        Marque los activos que está recibiendo. En herramientas
                        por cantidad puede indicar cuántas unidades regresaron.
                    </div>

                    @error('seleccionados')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    @foreach ($prestamoSeleccionado->detalles as $detalle)
                        @php
                            $esPorCantidad = $detalle->herramienta_id !== null
                                && $detalle->herramienta?->tipo_control === 'cantidad';

                            $nombreActivo = $detalle->equipo_id !== null
                                ? trim(
                                    ($detalle->equipo?->tipoEquipo?->nombre ?? 'Equipo')
                                    . ' '
                                    . ($detalle->equipo?->marca?->nombre ?? '')
                                    . ' '
                                    . ($detalle->equipo?->modelo ?? '')
                                )
                                : ($detalle->herramienta?->nombre ?? 'Herramienta');

                            $permiteDecimales = $esPorCantidad
                                && (bool) $detalle->herramienta?->unidadMedida?->permite_decimales;
                        @endphp

                        <div
                            wire:key="detalle-devolucion-{{ $detalle->id }}"
                            class="rounded-xl border border-gray-200 p-4"
                        >
                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    wire:model.live="seleccionados.{{ $detalle->id }}"
                                    class="mt-1 rounded border-gray-300"
                                >

                                <span class="flex-1">
                                    <span class="block font-semibold text-gray-800">
                                        {{ $nombreActivo }}
                                    </span>

                                    <span class="mt-1 block text-sm text-gray-500">
                                        Pendiente de devolución:
                                        {{ number_format($detalle->cantidad_pendiente, 2) }}
                                    </span>
                                </span>
                            </label>

                            @if ($seleccionados[$detalle->id] ?? false)
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">
                                            Cantidad que devuelve *
                                        </label>

                                        @if ($esPorCantidad)
                                            <input
                                                type="number"
                                                min="{{ $permiteDecimales ? '0.01' : '1' }}"
                                                max="{{ $detalle->cantidad_pendiente }}"
                                                step="{{ $permiteDecimales ? '0.01' : '1' }}"
                                                wire:model="cantidades.{{ $detalle->id }}"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >
                                        @else
                                            <input
                                                type="number"
                                                value="1"
                                                readonly
                                                class="w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2"
                                            >
                                        @endif

                                        @error("cantidades.$detalle->id")
                                            <p class="mt-1 text-sm text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">
                                            Observaciones
                                        </label>

                                        <textarea
                                            wire:model="observacionesDetalle.{{ $detalle->id }}"
                                            rows="2"
                                            placeholder="Estado en que se recibe..."
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                        ></textarea>

                                        @error("observacionesDetalle.$detalle->id")
                                            <p class="mt-1 text-sm text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

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
