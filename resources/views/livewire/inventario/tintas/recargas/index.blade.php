<div>

    {{-- Encabezado --}}
    <div class="mb-6 flex items-center justify-between">

        <div>

            <h1 class="text-2xl font-bold text-gray-800">
                Recargas de tinta
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro de recargas realizadas a impresoras.
            </p>

        </div>


        <button
            type="button"
            wire:click="nuevaRecarga"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva recarga
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
            placeholder="Impresora, inventario, tinta, color o técnico..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2"
        >

    </div>


    {{-- Historial --}}
    <div class="space-y-4">

        @forelse ($recargas as $recarga)

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">


                {{-- Cabecera --}}
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">

                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">


                        <div>

                            <div class="font-semibold text-gray-800">

                                {{ $recarga->equipo->marca?->nombre }}

                                {{ $recarga->equipo->modelo }}

                            </div>


                            <div class="mt-1 text-sm text-gray-500">

                                Inventario:
                                {{ $recarga->equipo->numero_inventario }}

                                <span class="mx-2">
                                    •
                                </span>

                                {{ $recarga->created_at->format('d/m/Y H:i') }}

                            </div>

                        </div>


                        <div class="text-sm text-gray-600">

                            Técnico:

                            <span class="font-medium">
                                {{ $recarga->usuario->name }}
                            </span>

                        </div>

                    </div>

                </div>


                {{-- Detalles --}}
                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="text-xs uppercase text-gray-500">

                                <th class="px-6 py-3 text-left">
                                    Tinta
                                </th>

                                <th class="px-6 py-3 text-center">
                                    Antes
                                </th>

                                <th class="px-6 py-3 text-center">
                                    Agregado
                                </th>

                                <th class="px-6 py-3 text-center">
                                    Después
                                </th>

                            </tr>

                        </thead>


                        <tbody class="divide-y divide-gray-100">

                            @foreach ($recarga->detalles as $detalle)

                                <tr>

                                    <td class="px-6 py-3">

                                        <div class="font-medium text-gray-800">
                                            {{ $detalle->tinta->nombre }}
                                        </div>

                                        <div class="text-sm text-gray-500">

                                            {{ $detalle->tinta->color }}

                                            @if ($detalle->tinta->marca)

                                                —
                                                {{ $detalle->tinta->marca->nombre }}

                                            @endif

                                        </div>

                                    </td>


                                    <td class="px-6 py-3 text-center text-gray-600">

                                        {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->porcentaje_antes,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }} %

                                    </td>


                                    <td class="px-6 py-3 text-center font-medium text-blue-600">

                                        + {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->porcentaje_agregado,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }} %

                                    </td>


                                    <td class="px-6 py-3 text-center font-semibold text-green-700">

                                        {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $detalle->porcentaje_despues,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }} %

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                @if ($recarga->observaciones)

                    <div class="border-t border-gray-100 px-6 py-4">

                        <p class="text-sm text-gray-600">

                            <span class="font-medium">
                                Observaciones:
                            </span>

                            {{ $recarga->observaciones }}

                        </p>

                    </div>

                @endif

            </div>


        @empty

            <div class="rounded-xl bg-white px-6 py-12 text-center text-gray-500 shadow-sm">

                No existen recargas de tinta registradas.

            </div>

        @endforelse

    </div>


    <div class="mt-6">
        {{ $recargas->links() }}
    </div>


    {{-- Modal --}}
    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white p-6 shadow-xl">


                {{-- Cabecera modal --}}
                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            Nueva recarga de tinta
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Puede registrar varios colores en una sola operación.
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
                    wire:submit="guardarRecarga"
                    class="space-y-6"
                >


                    {{-- Impresora --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Impresora *
                        </label>


                        <select
                            wire:model="equipoId"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                            <option value="">
                                Seleccione una impresora...
                            </option>


                            @foreach ($equipos as $equipo)

                                <option value="{{ $equipo->id }}">

                                    {{ $equipo->marca?->nombre }}

                                    {{ $equipo->modelo }}

                                    — Inv.
                                    {{ $equipo->numero_inventario }}

                                    @if ($equipo->departamento)

                                        — {{ $equipo->departamento->nombre }}

                                    @endif

                                </option>

                            @endforeach

                        </select>


                        @error('equipoId')

                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- Colores --}}
                    <div>

                        <div class="mb-3 flex items-center justify-between">

                            <div>

                                <h3 class="font-semibold text-gray-800">
                                    Tintas utilizadas
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Agregue una fila por cada color recargado.
                                </p>

                            </div>


                            <button
                                type="button"
                                wire:click="agregarDetalle"
                                class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50"
                            >
                                + Agregar otro color
                            </button>

                        </div>


                        <div class="space-y-4">


                            @foreach ($detalles as $indice => $detalle)

                                <div
                                    wire:key="detalle-recarga-{{ $indice }}"
                                    class="rounded-xl border border-gray-200 p-4"
                                >


                                    <div class="mb-4 flex items-center justify-between">

                                        <span class="text-sm font-semibold text-gray-700">
                                            Color {{ $indice + 1 }}
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


                                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">


                                        {{-- Tinta --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Tinta *
                                            </label>


                                            <select
                                                wire:model="detalles.{{ $indice }}.tinta_id"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2"
                                            >

                                                <option value="">
                                                    Seleccione...
                                                </option>


                                                @foreach ($tintas as $tinta)

                                                    @php

                                                        $existencia =
                                                            $tinta->existencia_equivalente;

                                                    @endphp


                                                    <option value="{{ $tinta->id }}">

                                                        {{ $tinta->nombre }}

                                                        — {{ $tinta->color }}

                                                        — {{ number_format(
                                                            $existencia,
                                                            2
                                                        ) }} bot.

                                                    </option>

                                                @endforeach

                                            </select>


                                            @error("detalles.$indice.tinta_id")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Antes --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Nivel antes *
                                            </label>


                                            <div class="relative">

                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    wire:model.live="detalles.{{ $indice }}.porcentaje_antes"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9"
                                                >

                                                <span class="absolute right-3 top-2 text-gray-500">
                                                    %
                                                </span>

                                            </div>


                                            @error("detalles.$indice.porcentaje_antes")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Agregado --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Agregado *
                                            </label>


                                            <div class="relative">

                                                <input
                                                    type="number"
                                                    min="0.01"
                                                    max="100"
                                                    step="0.01"
                                                    wire:model.live="detalles.{{ $indice }}.porcentaje_agregado"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9"
                                                >

                                                <span class="absolute right-3 top-2 text-gray-500">
                                                    %
                                                </span>

                                            </div>


                                            @error("detalles.$indice.porcentaje_agregado")

                                                <p class="mt-1 text-sm text-red-600">
                                                    {{ $message }}
                                                </p>

                                            @enderror

                                        </div>


                                        {{-- Después --}}
                                        <div>

                                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                                Nivel después
                                            </label>


                                            @php

                                                $antes =
                                                    (float) (
                                                        $detalle['porcentaje_antes']
                                                        ?? 0
                                                    );

                                                $agregado =
                                                    (float) (
                                                        $detalle['porcentaje_agregado']
                                                        ?? 0
                                                    );

                                                $despues =
                                                    $antes + $agregado;

                                            @endphp


                                            <div
                                                class="rounded-lg border px-3 py-2 font-semibold
                                                {{ $despues > 100
                                                    ? 'border-red-300 bg-red-50 text-red-600'
                                                    : 'border-green-200 bg-green-50 text-green-700'
                                                }}"
                                            >

                                                {{ rtrim(
                                                    rtrim(
                                                        number_format(
                                                            $despues,
                                                            2,
                                                            '.',
                                                            ''
                                                        ),
                                                        '0'
                                                    ),
                                                    '.'
                                                ) }} %

                                            </div>

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
                            placeholder="Información adicional sobre la recarga..."
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
                            Registrar recarga
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>