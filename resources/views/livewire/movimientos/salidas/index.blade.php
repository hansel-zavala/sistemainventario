<div>

    {{-- Encabezado --}}
    <div class="mb-6 flex items-center justify-between">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Salidas de inventario
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro de artículos que salen del inventario.
            </p>

        </div>


        <button
            type="button"
            wire:click="nuevaSalida"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva salida
        </button>

    </div>


    {{-- Mensaje --}}
    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    {{-- Buscador --}}
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <label class="mb-1 block text-sm font-medium text-gray-700">
            Buscar
        </label>

        <input
            type="text"
            wire:model.live.debounce.300ms="buscar"
            placeholder="Observaciones o usuario..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2"
        >

    </div>


    {{-- Historial --}}
    <div class="space-y-4">


        @forelse ($movimientos as $movimiento)

            <div class="rounded-xl bg-white shadow-sm">


                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">

                    <div class="flex flex-col justify-between gap-2 md:flex-row">


                        <div>

                            <span class="font-semibold text-gray-800">
                                Salida #{{ $movimiento->id }}
                            </span>


                            <div class="mt-1 text-sm text-gray-500">

                                {{ $movimiento
                                    ->fecha_movimiento
                                    ->format('d/m/Y H:i')
                                }}

                            </div>

                            <div class="mt-2 text-sm text-gray-600">

                                <span class="font-medium">
                                    Destino:
                                </span>

                                {{ $movimiento->departamentoDestino?->nombre ?? 'No disponible' }}

                                @if ($movimiento->departamentoDestino?->sigla)

                                    ({{ $movimiento->departamentoDestino->sigla }})

                                @endif

                            </div>


                            <div class="mt-1 text-sm text-gray-600">

                                <span class="font-medium">
                                    Recibe:
                                </span>

                                {{ $movimiento->persona_recibe }}

                            </div>


                            <div class="mt-1 text-sm text-gray-600">

                                <span class="font-medium">
                                    Motivo:
                                </span>

                                {{ $movimiento->motivo }}

                            </div>

                        </div>


                        <div class="text-sm text-gray-600">

                            Registrado por:

                            <span class="font-medium">
                                {{ $movimiento->usuario->name }}
                            </span>

                        </div>

                    </div>

                </div>


                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="text-xs uppercase text-gray-500">

                                <th class="px-6 py-3 text-left">
                                    Tipo
                                </th>

                                <th class="px-6 py-3 text-left">
                                    Artículo
                                </th>

                                <th class="px-6 py-3 text-right">
                                    Anterior
                                </th>

                                <th class="px-6 py-3 text-right">
                                    Salida
                                </th>

                                <th class="px-6 py-3 text-right">
                                    Posterior
                                </th>

                            </tr>

                        </thead>


                        <tbody class="divide-y divide-gray-100">


                            @foreach ($movimiento->detalles as $detalle)

                                @php
                                    $item =
                                        $detalle->obtenerItem();
                                @endphp


                                <tr>

                                    <td class="px-6 py-3 text-gray-600">

                                        {{ ucfirst(
                                            $detalle->tipo_item
                                        ) }}

                                    </td>


                                    <td class="px-6 py-3 font-medium text-gray-800">

                                        {{ $item?->nombre
                                            ?? 'Artículo no disponible'
                                        }}


                                        @if (
                                            $detalle->tipo_item === 'tinta'
                                            && $item
                                        )

                                            <span class="text-sm font-normal text-gray-500">
                                                — {{ $item->color }}
                                            </span>

                                        @endif

                                    </td>


                                    <td class="px-6 py-3 text-right text-gray-600">

                                        {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->existencia_anterior,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }}

                                    </td>


                                    <td class="px-6 py-3 text-right font-semibold text-red-600">

                                        - {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->cantidad,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }}

                                    </td>


                                    <td class="px-6 py-3 text-right font-medium text-gray-800">

                                        {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->existencia_posterior,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }}

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                @if ($movimiento->observaciones)

                    <div class="border-t border-gray-100 px-6 py-4 text-sm text-gray-600">

                        <span class="font-medium">
                            Observaciones:
                        </span>

                        {{ $movimiento->observaciones }}

                    </div>

                @endif

            </div>


        @empty

            <div class="rounded-xl bg-white px-6 py-12 text-center text-gray-500 shadow-sm">

                No existen salidas registradas.

            </div>

        @endforelse

    </div>


    <div class="mt-6">
        {{ $movimientos->links() }}
    </div>


    {{-- Modal --}}
    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl">


                <div class="mb-6 flex items-center justify-between">


                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            Nueva salida de inventario
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Puede registrar varios artículos en una sola salida.
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


                <form
                    wire:submit="guardarSalida"
                    class="space-y-6"
                >


                    {{-- Artículos --}}
                    <div>


                        <div class="mb-3 flex items-center justify-between">

                        {{-- Datos de destino --}}
                            <div>

                                <h3 class="mb-1 font-semibold text-gray-800">
                                    Datos de destino
                                </h3>

                                <p class="mb-4 text-sm text-gray-500">
                                    Indique dónde se entregarán los artículos y quién los recibe.
                                </p>


                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


                                    {{-- Departamento --}}
                                    <div>

                                        <label class="mb-1 block text-sm font-medium text-gray-700">
                                            Departamento destino *
                                        </label>

                                        <select
                                            wire:model="departamentoDestinoId"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                        >

                                            <option value="">
                                                Seleccione...
                                            </option>

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
                                            <p class="mt-1 text-sm text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                    </div>


                                    {{-- Persona recibe --}}
                                    <div>

                                        <label class="mb-1 block text-sm font-medium text-gray-700">
                                            Persona que recibe *
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="personaRecibe"
                                            placeholder="Ej. Juan Pérez"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                        >

                                        @error('personaRecibe')
                                            <p class="mt-1 text-sm text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                    </div>


                                    {{-- Motivo --}}
                                    <div class="md:col-span-2">

                                        <label class="mb-1 block text-sm font-medium text-gray-700">
                                            Motivo de la salida *
                                        </label>

                                        <input
                                            type="text"
                                            wire:model="motivo"
                                            placeholder="Ej. Instalación de puntos de red en Catastro"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                        >

                                        @error('motivo')
                                            <p class="mt-1 text-sm text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                    </div>

                                </div>

                            </div>


                            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                                <div>

                                    <h3 class="font-semibold text-gray-800">
                                        Artículos entregados
                                    </h3>

                                    <p class="mt-1 text-sm text-gray-500">
                                        Agregue una fila por cada artículo.
                                    </p>

                                </div>


                                <button
                                    type="button"
                                    wire:click="agregarDetalle"
                                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                                >
                                    + Agregar artículo
                                </button>

                            </div>

                        </div>


                        <div class="space-y-4">


                            @foreach ($detalles as $indice => $detalle)


                                <div
                                    wire:key="salida-detalle-{{ $indice }}"
                                    class="rounded-xl border border-gray-200 p-4"
                                >


                                    <div class="mb-4 flex items-center justify-between">


                                        <span class="text-sm font-semibold text-gray-700">

                                            Artículo {{ $indice + 1 }}

                                        </span>


                                        @if (count($detalles) > 1)

                                            <button
                                                type="button"
                                                wire:click="eliminarDetalle({{ $indice }})"
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
                                                Tipo *
                                            </label>


                                            <select
                                                wire:model.live="detalles.{{ $indice }}.tipo_item"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>

                                                <option value="herramienta">
                                                    Herramienta
                                                </option>

                                                <option value="insumo">
                                                    Insumo
                                                </option>

                                                <option value="tinta">
                                                    Tinta
                                                </option>

                                            </select>


                                            @error("detalles.$indice.tipo_item")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Artículo --}}
                                        <div>


                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Artículo *
                                            </label>


                                            <select
                                                wire:model="detalles.{{ $indice }}.item_id"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                                @disabled(
                                                    empty(
                                                        $detalle['tipo_item']
                                                    )
                                                )
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>


                                                @if (
                                                    $detalle['tipo_item']
                                                    === 'herramienta'
                                                )

                                                    @foreach ($herramientas as $herramienta)

                                                        <option value="{{ $herramienta->id }}">

                                                            {{ $herramienta->nombre }}

                                                            —
                                                            Disponible:
                                                            {{ rtrim(
                                                                rtrim(
                                                                    number_format(
                                                                        (float) $herramienta->cantidad_actual,
                                                                        2,
                                                                        '.',
                                                                        ''
                                                                    ),
                                                                    '0'
                                                                ),
                                                                '.'
                                                            ) }}

                                                        </option>

                                                    @endforeach


                                                @elseif (
                                                    $detalle['tipo_item']
                                                    === 'insumo'
                                                )

                                                    @foreach ($insumos as $insumo)

                                                        <option value="{{ $insumo->id }}">

                                                            {{ $insumo->nombre }}

                                                            —
                                                            Disponible:
                                                            {{ rtrim(
                                                                rtrim(
                                                                    number_format(
                                                                        (float) $insumo->cantidad_actual,
                                                                        2,
                                                                        '.',
                                                                        ''
                                                                    ),
                                                                    '0'
                                                                ),
                                                                '.'
                                                            ) }}

                                                        </option>

                                                    @endforeach


                                                @elseif (
                                                    $detalle['tipo_item']
                                                    === 'tinta'
                                                )

                                                    @foreach ($tintas as $tinta)

                                                        <option value="{{ $tinta->id }}">

                                                            {{ $tinta->nombre }}
                                                            — {{ $tinta->color }}

                                                            @if ($tinta->marca)
                                                                — {{ $tinta->marca->nombre }}
                                                            @endif

                                                            — {{ $tinta->botellas_completas }}
                                                            bot.

                                                        </option>

                                                    @endforeach

                                                @endif

                                            </select>


                                            @error("detalles.$indice.item_id")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Cantidad --}}
                                        <div>


                                            <label class="mb-1 block text-sm font-medium text-gray-700">

                                                @if (
                                                    $detalle['tipo_item']
                                                    === 'tinta'
                                                )

                                                    Botellas a retirar *

                                                @else

                                                    Cantidad de salida *

                                                @endif

                                            </label>


                                            <input
                                                type="number"
                                                min="{{ $detalle['tipo_item'] === 'tinta'
                                                    ? '1'
                                                    : '0.01'
                                                }}"
                                                step="{{ $detalle['tipo_item'] === 'tinta'
                                                    ? '1'
                                                    : '0.01'
                                                }}"
                                                wire:model="detalles.{{ $indice }}.cantidad"
                                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                            >


                                            @if (
                                                $detalle['tipo_item']
                                                === 'tinta'
                                            )

                                                <p class="mt-1 text-xs text-gray-500">
                                                    Solo se pueden retirar botellas completas.
                                                </p>

                                            @endif


                                            @error("detalles.$indice.cantidad")

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
                            placeholder="Ej. Material entregado para uso del Departamento de Informática."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>


                        @error('observaciones')

                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- Botones --}}
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
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        >
                            Registrar salida
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>