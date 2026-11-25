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

    @php
        /** @var string $filtroEstado */
        /** @var string $orden */
        /** @var string $tipoArticulo */
        /** @var string $destino */
        
        $types = \App\Services\PrestamosTable::TYPES;
        $columns = \App\Services\PrestamosTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;

        $states = \App\Services\PrestamosTable::STATES;
        $activeFilters = array_filter([
            'filtroEstado' => isset($states[$filtroEstado]) ? 'Estado: '.$states[$filtroEstado] : null,
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'tipoArticulo' => isset($types[$tipoArticulo]) ? 'Tipo de activo: '.$types[$tipoArticulo] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
            'destino' => isset($destinationOptions[$destino]) ? 'Departamento destino: '.$destinationOptions[$destino] : null,
        ]);
    @endphp

    <x-data-table id="prestamos" label="Listado de préstamos" :columns="$columns"
        :show-columns="false" :sortable="false" :show-actions="false"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar préstamos"
        search-placeholder="Activo, código, persona, departamento o usuario...">
        <x-slot:filters>
            <label class="text-sm font-medium text-muted sm:col-span-1">Estado
                <select wire:model.live="filtroEstado" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
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
                <select wire:model.live="destino" @if ($filterErrors->has('destino')) aria-invalid="true" aria-describedby="prestamos-filter-error" @endif
                    class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    <option value="">Todos</option>
                    @foreach ($destinationOptions as $value => $label)
                        <option wire:key="prestamos-destino-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-muted sm:col-span-1">Orden por fecha
                <select wire:model.live="orden" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                    @foreach ($orders as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
                <label wire:key="prestamos-filter-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="prestamos-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="prestamos" :action="route('movimientos.prestamos.exportar')" :buscar="$buscar"
                :visible-columns="array_keys($columns)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="prestamos-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $prestamos->count() }} de {{ $prestamos->total() }} préstamos encontrados
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="prestamos-filter-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>
        <x-slot:content>
            <div class="space-y-3 p-3 sm:p-4">
                @forelse ($prestamos as $prestamo)
                    @include('livewire.movimientos.prestamos.tarjeta-historial', ['prestamo' => $prestamo])
                @empty
                    <p class="rounded-xl border border-line bg-canvas px-4 py-10 text-center text-sm text-muted">
                        {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver los préstamos.' : 'No se encontraron préstamos.' }}
                    </p>
                @endforelse
            </div>
        </x-slot:content>
        <x-slot:pagination>
            <x-table-pagination :paginator="$prestamos" label="Paginación de préstamos" />
        </x-slot:pagination>
    </x-data-table>

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
