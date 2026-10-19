<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Tinta
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Control de botellas de tinta disponibles para impresoras.
            </p>
        </div>


        <button
            type="button"
            wire:click="crearTinta"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
            + Nueva tinta
        </button>

    </div>


    @if (session()->has('mensaje'))

    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
        {{ session('mensaje') }}
    </div>

    @endif


    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">


            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Referencia, marca, color..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">

            </div>


            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Color
                </label>

                <select
                    wire:model.live="filtroColor"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">

                    <option value="">
                        Todos
                    </option>

                    @foreach ($colores as $color)

                    <option value="{{ $color }}">
                        {{ $color }}
                    </option>

                    @endforeach

                </select>

            </div>


            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Estado
                </label>

                <select
                    wire:model.live="filtroEstado"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2">

                    <option value="">
                        Todos
                    </option>

                    <option value="activo">
                        Activos
                    </option>

                    <option value="inactivo">
                        Inactivos
                    </option>

                </select>

            </div>

        </div>

    </div>


    <div class="overflow-hidden rounded-xl bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Tinta
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Color
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Presentación
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Existencia
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Estado
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">

                    @forelse ($tintas as $tinta)

                    @php

                    $equivalente =
                    $tinta->existencia_equivalente;

                    $stockBajo =
                    $equivalente
                    <= (float) $tinta->stock_minimo_botellas;

                        @endphp


                        <tr class="hover:bg-gray-50">


                            <td class="px-5 py-4">

                                <div class="font-medium text-gray-800">
                                    {{ $tinta->nombre }}
                                </div>

                                @if ($tinta->marca)

                                <div class="text-sm text-gray-500">
                                    {{ $tinta->marca->nombre }}
                                </div>

                                @endif

                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $tinta->color }}
                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $tinta->presentacion ?? '—' }}
                            </td>


                            <td class="px-5 py-4">

                                <div
                                    class="{{ $stockBajo
                                        ? 'font-semibold text-red-600'
                                        : 'font-medium text-gray-700'
                                    }}">
                                    {{ $tinta->botellas_completas }}

                                    {{ $tinta->botellas_completas == 1
                                        ? 'botella completa'
                                        : 'botellas completas'
                                    }}
                                </div>


                                @if ((float) $tinta->porcentaje_botella_abierta > 0)

                                <div class="mt-1 text-sm text-gray-600">
                                    + {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $tinta->porcentaje_botella_abierta,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }} % de una botella abierta
                                </div>

                                @endif


                                <div class="mt-1 text-xs text-gray-500">
                                    Equivalente:
                                    {{ number_format($equivalente, 2) }}
                                    botellas
                                </div>


                                @if ($stockBajo)

                                <div class="mt-1 text-xs font-medium text-red-600">
                                    Stock bajo
                                </div>

                                @endif

                            </td>


                            <td class="px-5 py-4">

                                @if ($tinta->activo)

                                <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                    Activa
                                </span>

                                @else

                                <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                    Inactiva
                                </span>

                                @endif

                            </td>


                            <td class="px-5 py-4 text-right">

                                <div class="flex justify-end gap-3">

                                    <button
                                        type="button"
                                        wire:click="editarTinta({{ $tinta->id }})"
                                        class="font-medium text-blue-600 hover:text-blue-800">
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $tinta->id }})"
                                        class="{{ $tinta->activo
                                            ? 'font-medium text-red-600 hover:text-red-800'
                                            : 'font-medium text-green-600 hover:text-green-800'
                                        }}">
                                        {{ $tinta->activo
                                            ? 'Desactivar'
                                            : 'Activar'
                                        }}
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="abrirHistorial({{ $tinta->id }})"
                                        class="font-medium text-indigo-600 hover:text-indigo-800">
                                        Historial
                                    </button>


                                </div>

                            </td>

                        </tr>


                        @empty

                        <tr>

                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500">
                                No se encontraron tintas registradas.
                            </td>

                        </tr>

                        @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $tintas->links() }}
        </div>

    </div>


    @if ($mostrarModal)

    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

        <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">


            <div class="mb-6 flex items-center justify-between">

                <div>

                    <h2 class="text-xl font-bold text-gray-800">
                        {{ $tintaId
                                ? 'Editar tinta'
                                : 'Nueva tinta'
                            }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Registre las botellas completas y, si existe, el contenido de una botella abierta.
                    </p>

                </div>


                <button
                    type="button"
                    wire:click="cerrarModal"
                    class="text-2xl text-gray-400 hover:text-gray-600">
                    ×
                </button>

            </div>


            <form
                wire:submit="guardarTinta"
                class="space-y-5">

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nombre o referencia *
                        </label>

                        <input
                            type="text"
                            wire:model="nombre"
                            placeholder="Ej. Epson 544"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2">

                        @error('nombre')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Marca
                        </label>

                        <select
                            wire:model="marcaId"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2">

                            <option value="">
                                Sin marca
                            </option>

                            @foreach ($marcas as $marca)

                            <option value="{{ $marca->id }}">
                                {{ $marca->nombre }}
                            </option>

                            @endforeach

                        </select>

                    </div>


                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Color *
                        </label>

                        <input
                            type="text"
                            wire:model="color"
                            placeholder="Ej. Negro"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2">

                        @error('color')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Presentación
                        </label>

                        <input
                            type="text"
                            wire:model="presentacion"
                            placeholder="Ej. Botella 65 ml"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2">

                    </div>



                    {{-- Botellas completas --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Botellas completas *
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="1"
                            wire:model="botellasCompletas"
                            @readonly($tintaId !==null)
                            class="w-full rounded-lg border border-gray-300 px-4 py-2
                                    {{ $tintaId !== null
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-600'
                                        : ''
                                    }}">

                        @if ($tintaId !== null)

                        <p class="mt-1 text-xs text-gray-500">
                            Las botellas completas se modifican mediante
                            Movimientos → Entradas o Salidas.
                        </p>

                        @else

                        <p class="mt-1 text-xs text-gray-500">
                            Registre únicamente las botellas completas
                            disponibles inicialmente.
                        </p>

                        @endif

                        @error('botellasCompletas')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Contenido de botella abierta --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Contenido de botella abierta
                        </label>

                        <div class="relative">

                            <input
                                type="number"
                                min="0"
                                max="99.99"
                                step="0.01"
                                wire:model="porcentajeBotellaAbierta"
                                @readonly($tintaId !==null)
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 pr-10
                                            {{ $tintaId !== null
                                                ? 'cursor-not-allowed bg-gray-100 text-gray-600'
                                                : ''
                                            }}">

                            <span class="pointer-events-none absolute right-4 top-2 text-gray-500">
                                %
                            </span>

                        </div>

                        @if ($tintaId !== null)

                        <p class="mt-1 text-xs text-gray-500">
                            Este porcentaje se actualiza mediante
                            las recargas de tinta.
                            No puede modificarse directamente.
                        </p>

                        @else

                        <p class="mt-1 text-xs text-gray-500">
                            Indique el porcentaje disponible de una
                            botella abierta. Si no existe, coloque 0 %.
                        </p>

                        @endif

                        @error('porcentajeBotellaAbierta')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>

                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Stock mínimo *
                        </label>

                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="stockMinimoBotellas"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2">

                        <p class="mt-1 text-xs text-gray-500">
                            Se expresa en botellas equivalentes. Ej.: 1 equivale a una botella.
                        </p>

                        @error('stockMinimoBotellas')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>

                </div>


                <div>

                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Observaciones
                    </label>

                    <textarea
                        wire:model="observaciones"
                        rows="4"
                        placeholder="Información adicional..."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2"></textarea>

                </div>

                <div class="flex items-center">

                    <input
                        id="activoTinta"
                        type="checkbox"
                        wire:model="activoTinta"
                        class="rounded border-gray-300">

                    <label
                        for="activoTinta"
                        class="ml-2 text-sm text-gray-700">
                        Tinta activa
                    </label>

                </div>


                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">

                    <button
                        type="button"
                        wire:click="cerrarModal"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>


                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
                        {{ $tintaId
                                ? 'Guardar cambios'
                                : 'Registrar tinta'
                            }}
                    </button>

                </div>

            </form>

        </div>

    </div>

    @endif


    {{-- Modal de historial completo de tinta --}}
    @if ($mostrarHistorial)

    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

        <div class="mx-auto w-full max-w-6xl rounded-xl bg-white p-6 shadow-xl">

            {{-- Encabezado --}}
            <div class="mb-6 flex items-start justify-between gap-4">

                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        Historial completo de tinta
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        {{ $historialTintaNombre }}
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="cerrarHistorial"
                    class="text-2xl text-gray-400 hover:text-gray-600">
                    &times;
                </button>

            </div>

            <p class="mb-4 text-sm text-gray-600">
                Consulte las aperturas, entradas, salidas
                y recargas realizadas con esta tinta.
            </p>

            @if ($eventosHistorial->isEmpty())

            <div class="rounded-lg bg-gray-50 px-5 py-10 text-center text-sm text-gray-500">
                Esta tinta todavía no tiene movimientos
                registrados en el historial.
            </div>

            @else

            <div class="overflow-x-auto rounded-lg border border-gray-200">

                <table class="w-full text-sm">

                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">

                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Movimiento</th>
                            <th class="px-4 py-3">Cantidad</th>
                            <th class="px-4 py-3">Anterior</th>
                            <th class="px-4 py-3">Posterior</th>
                            <th class="px-4 py-3">Responsable / Detalle</th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200">

                        @foreach ($eventosHistorial as $evento)

                        <tr
                            wire:key="historial-tinta-{{ $loop->index }}"
                            class="hover:bg-gray-50">

                            {{-- Fecha --}}
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">

                                {{ \Illuminate\Support\Carbon::parse(
                                            $evento['fecha']
                                        )->format('d/m/Y H:i') }}

                            </td>

                            {{-- Movimiento --}}
                            <td class="px-4 py-3">

                                @switch($evento['tipo'])

                                @case('apertura')
                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                    Apertura
                                </span>
                                @break

                                @case('entrada')
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                    Entrada
                                </span>
                                @break

                                @case('salida')
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                    Salida
                                </span>
                                @break

                                @case('recarga')
                                <span class="rounded-full bg-purple-100 px-2 py-1 text-xs font-semibold text-purple-800">
                                    Recarga
                                </span>
                                @break

                                @endswitch

                            </td>

                            {{-- Cantidad --}}
                            <td class="px-4 py-3 font-medium text-gray-800">

                                {{ number_format(
                                            (float) $evento['cantidad'],
                                            2,
                                            '.',
                                            ''
                                        ) }}

                                <div class="text-xs font-normal text-gray-500">
                                    {{ $evento['unidad'] }}
                                </div>

                            </td>

                            {{-- Existencia anterior --}}
                            <td class="px-4 py-3 text-gray-600">

                                {{ $evento['anterior'] !== null
                                            ? number_format(
                                                (float) $evento['anterior'],
                                                2,
                                                '.',
                                                ''
                                            )
                                            : '—'
                                        }}

                            </td>

                            {{-- Existencia posterior --}}
                            <td class="px-4 py-3 text-gray-600">

                                {{ $evento['posterior'] !== null
                                            ? number_format(
                                                (float) $evento['posterior'],
                                                2,
                                                '.',
                                                ''
                                            )
                                            : '—'
                                        }}

                            </td>

                            {{-- Responsable y detalles --}}
                            <td class="min-w-64 px-4 py-3">

                                <div class="font-medium text-gray-800">
                                    {{ $evento['responsable'] }}
                                </div>

                                @if ($evento['detalle'] !== '')

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $evento['detalle'] }}
                                </div>

                                @endif

                            </td>

                        </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            <p class="mt-3 text-xs text-gray-500">
                Se muestran hasta 50 operaciones recientes.
                Las existencias anteriores y posteriores
                de las recargas no se almacenaban como saldos
                independientes en el registro de recarga.
                El guion no significa existencia cero.
            </p>

            @endif

            {{-- Botón de cierre --}}
            <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">

                <button
                    type="button"
                    wire:click="cerrarHistorial"
                    class="rounded-lg bg-gray-800 px-5 py-2 font-medium text-white hover:bg-gray-900">
                    Cerrar
                </button>

            </div>

        </div>

    </div>

    @endif


</div>