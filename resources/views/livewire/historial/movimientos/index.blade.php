<div>

    {{-- Encabezado --}}
    <div class="mb-6">

        <h1 class="text-2xl font-bold text-gray-800">
            Historial de movimientos
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Consulta centralizada de los movimientos
            realizados en el Departamento de Informática.
        </p>

    </div>

    {{-- Filtros --}}
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <h2 class="mb-4 text-base font-semibold text-gray-800">
            Filtros de búsqueda
        </h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

            {{-- Buscar --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Artículo, código o responsable..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
            </div>

            {{-- Tipo de movimiento --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Tipo de movimiento
                </label>

                <select
                    wire:model.live="filtroTipo"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
                    <option value="">Todos los movimientos</option>
                    <option value="apertura">Aperturas</option>
                    <option value="entrada">Entradas</option>
                    <option value="salida">Salidas</option>
                    <option value="prestamo">Préstamos</option>
                    <option value="devolucion">Devoluciones</option>
                    <option value="recarga">Recargas de tinta</option>
                </select>
            </div>


            {{-- Tipo de artículo --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Tipo de artículo
                </label>

                <select
                    wire:model.live="filtroArticuloTipo"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
                    <option value="">Todos los artículos</option>
                    <option value="equipo">Equipos tecnológicos</option>
                    <option value="herramienta">Herramientas</option>
                    <option value="insumo">Insumos</option>
                    <option value="tinta">Tinta</option>
                </select>
            </div>


            {{-- Usuario responsable --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Responsable
                </label>

                <select
                    wire:model.live="filtroResponsable"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
                    <option value="">Todos los responsables</option>

                    @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}">
                        {{ $usuario->name }}
                    </option>
                    @endforeach
                </select>
            </div>



            {{-- Fecha desde --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Fecha desde
                </label>

                <input
                    type="date"
                    wire:model.live="fechaDesde"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
            </div>

            {{-- Fecha hasta --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Fecha hasta
                </label>

                <input
                    type="date"
                    wire:model.live="fechaHasta"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">
            </div>

        </div>

        <div class="mt-4 flex justify-end">

            <button
                type="button"
                wire:click="limpiarFiltros"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Limpiar filtros
            </button>

        </div>

    </div>


    {{-- Tabla de historial --}}

    {{-- Historial centralizado --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-gray-800">
                Registro de movimientos
            </h2>

            <p class="mt-1 text-xs text-gray-500">
                Consulta de aperturas, entradas, salidas,
                préstamos, devoluciones y recargas de tinta.
            </p>
        </div>

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-5 py-3">Movimiento</th>
                        <th class="px-5 py-3">Artículo</th>
                        <th class="px-5 py-3">Cantidad</th>
                        <th class="px-5 py-3">Responsable</th>
                        <th class="px-5 py-3">Detalles</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">

                    @forelse ($eventosHistorial as $evento)

                    <tr
                        wire:key="historial-{{ $eventosHistorial->currentPage() }}-{{ $loop->index }}-{{ $evento->tipo }}-{{ $evento->tipo_item }}-{{ $evento->item_id }}-{{ $evento->origen_id }}"
                        class="hover:bg-gray-50">

                        {{-- Fecha --}}
                        <td class="whitespace-nowrap px-5 py-4 text-gray-600">
                            {{ \Illuminate\Support\Carbon::parse(
                                $evento->fecha
                            )->format('d/m/Y H:i') }}
                        </td>

                        {{-- Operación --}}
                        <td class="px-5 py-4">

                            @switch($evento->tipo)

                            @case('apertura')
                            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                Apertura
                            </span>
                            @break

                            @case('entrada')
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                                Entrada
                            </span>
                            @break

                            @case('salida')
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                Salida
                            </span>
                            @break

                            @case('prestamo')
                            <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-800">
                                Préstamo
                            </span>
                            @break

                            @case('devolucion')
                            <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800">
                                Devolución
                            </span>
                            @break

                            @case('recarga')
                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
                                Recarga
                            </span>
                            @break

                            @endswitch

                        </td>

                        {{-- Artículo --}}
                        <td class="px-5 py-4">

                            <div class="font-medium text-gray-800">
                                {{ $evento->articulo }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                @switch($evento->tipo_item)
                                @case('herramienta')
                                Herramienta
                                @break
                                @case('insumo')
                                Insumo
                                @break
                                @case('tinta')
                                Tinta
                                @break
                                @case('equipo')
                                Equipo tecnológico
                                @break
                                @default
                                Artículo
                                @endswitch
                            </div>

                        </td>

                        {{-- Cantidad --}}
                        <td class="px-5 py-4">

                            <div class="font-semibold text-gray-800">
                                {{ number_format(
                                    (float) $evento->cantidad,
                                    2,
                                    '.',
                                    ''
                                ) }}
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ $evento->unidad }}
                            </div>

                        </td>

                        {{-- Responsable --}}
                        <td class="px-5 py-4 text-gray-600">
                            {{ $evento->responsable
                                ?? 'Usuario no disponible' }}
                        </td>

                        {{-- Información adicional --}}
                        <td class="min-w-64 px-5 py-4">

                            @if (
                            $evento->anterior !== null
                            && $evento->posterior !== null
                            )

                            <div class="text-xs text-gray-600">
                                Existencia anterior:
                                {{ number_format(
                                        (float) $evento->anterior,
                                        2,
                                        '.',
                                        ''
                                    ) }}
                            </div>

                            <div class="mt-1 text-xs text-gray-600">
                                Existencia posterior:
                                {{ number_format(
                                        (float) $evento->posterior,
                                        2,
                                        '.',
                                        ''
                                    ) }}
                            </div>

                            @endif

                            @if ($evento->detalle !== '')

                            <div class="mt-2 text-xs text-gray-500">
                                {{ $evento->detalle }}
                            </div>

                            @endif

                            @if (
                            $evento->anterior === null
                            && $evento->posterior === null
                            )

                            <div class="mt-1 text-xs text-gray-400">
                                Sin saldo de inventario registrado
                                en esta operación.
                            </div>

                            @endif

                        </td>

                    </tr>

                    @empty

                    <tr>
                        <td
                            colspan="6"
                            class="px-6 py-12 text-center text-gray-500">
                            No se encontraron movimientos
                            con los filtros seleccionados.
                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        <div class="border-t border-gray-200 px-5 py-4">
            {{ $eventosHistorial->links() }}
        </div>

    </div>



</div>