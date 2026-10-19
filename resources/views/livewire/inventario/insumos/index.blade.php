<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Insumos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de insumos del Departamento de Informática.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearInsumo"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo insumo
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Nombre, marca o categoría..."
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
                    <option value="inactivo">Inactivos</option>
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
                            Insumo
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Categoría
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Marca
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Existencia
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Stock mínimo
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

                    @forelse ($insumos as $insumo)

                        @php
                            $cantidad = (float) $insumo->cantidad_actual;

                            $unidadCantidad =
                                $cantidad == 1
                                    ? $insumo->unidadMedida->nombre_singular
                                    : $insumo->unidadMedida->nombre_plural;

                            $stockMinimo =
                                (float) $insumo->stock_minimo;

                            $unidadStock =
                                $stockMinimo == 1
                                    ? $insumo->unidadMedida->nombre_singular
                                    : $insumo->unidadMedida->nombre_plural;

                            $stockBajo =
                                $cantidad <= $stockMinimo;
                        @endphp


                        <tr class="hover:bg-gray-50">

                            <td class="px-5 py-4 font-medium text-gray-800">
                                {{ $insumo->nombre }}
                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $insumo->categoria->nombre }}
                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $insumo->marca?->nombre ?? '—' }}
                            </td>


                            <td class="px-5 py-4">

                                <span
                                    class="{{ $stockBajo
                                        ? 'font-semibold text-red-600'
                                        : 'text-gray-700'
                                    }}"
                                >
                                    {{ (float) number_format($cantidad, 2, '.', '') }}

                                    {{ $unidadCantidad }}
                                </span>


                                @if ($stockBajo)

                                    <div class="mt-1 text-xs text-red-600">
                                        Stock bajo
                                    </div>

                                @endif

                            </td>


                            <td class="px-5 py-4 text-gray-600">

                                {{ (float) number_format($stockMinimo, 2, '.', '') }}

                                {{ $unidadStock }}

                            </td>


                            <td class="px-5 py-4">

                                @if ($insumo->activo)

                                    <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                        Activo
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        Inactivo
                                    </span>

                                @endif

                            </td>


                            <td class="px-5 py-4 text-right">

                                <div class="flex justify-end gap-3">

                                    <button
                                        type="button"
                                        wire:click="editarInsumo({{ $insumo->id }})"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $insumo->id }})"
                                        class="{{ $insumo->activo
                                            ? 'font-medium text-red-600 hover:text-red-800'
                                            : 'font-medium text-green-600 hover:text-green-800'
                                        }}"
                                    >
                                        {{ $insumo->activo
                                            ? 'Desactivar'
                                            : 'Activar'
                                        }}
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="abrirHistorial({{ $insumo->id }})"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Historial
                                    </button>


                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                No se encontraron insumos registrados.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $insumos->links() }}
        </div>

    </div>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $insumoId
                                ? 'Editar insumo'
                                : 'Nuevo insumo'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información del insumo.
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

                @if (session()->has('mensajeCatalogo'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('mensajeCatalogo') }}
                    </div>
                @endif


                <form
                    wire:submit="guardarInsumo"
                    class="space-y-5"
                >

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>

                            <input
                                type="text"
                                wire:model="nombre"
                                placeholder="Ej. Conector RJ45"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('nombre')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Categoría --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Categoría *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="categoriaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($categorias as $categoria)
                                        <option value="{{ $categoria->id }}">
                                            {{ $categoria->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('categoria')"
                                    title="Nueva categoría"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('categoriaId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Marca --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Marca
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="marcaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Sin marca
                                    </option>

                                    @foreach ($marcas as $marca)
                                        <option value="{{ $marca->id }}">
                                            {{ $marca->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('marca')"
                                    title="Nueva marca"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('marcaId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Unidad --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Unidad de medida *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="unidadMedidaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($unidades as $unidad)
                                        <option value="{{ $unidad->id }}">
                                            {{ $unidad->nombre_singular }}
                                            @if ($unidad->abreviatura)
                                                ({{ $unidad->abreviatura }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('unidad_medida')"
                                    title="Nueva unidad de medida"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('unidadMedidaId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        
                        {{-- Cantidad actual --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Cantidad actual *
                            </label>

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model="cantidadActual"
                                @readonly($insumoId !== null)
                                class="w-full rounded-lg border border-gray-300 px-4 py-2
                                    {{ $insumoId !== null
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-600'
                                        : ''
                                    }}"
                            >

                                @if ($insumoId !== null)

                                    <p class="mt-1 text-xs text-gray-500">
                                        La existencia no puede modificarse desde este formulario.
                                        Utilice Movimientos → Entradas o Salidas.
                                    </p>

                                @else

                                    <p class="mt-1 text-xs text-gray-500">
                                        Registre la cantidad inicial del insumo.
                                        Las próximas modificaciones se realizarán mediante movimientos.
                                    </p>

                                @endif

                                @error('cantidadActual')
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
                                wire:model="stockMinimo"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            <p class="mt-1 text-xs text-gray-500">
                                Cuando la existencia llegue a este valor se considerará stock bajo.
                            </p>

                            @error('stockMinimo')
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
                            placeholder="Información adicional del insumo..."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>

                        @error('observaciones')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div class="flex items-center">

                        <input
                            id="activoInsumo"
                            type="checkbox"
                            wire:model="activoInsumo"
                            class="rounded border-gray-300"
                        >

                        <label
                            for="activoInsumo"
                            class="ml-2 text-sm text-gray-700"
                        >
                            Insumo activo
                        </label>

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
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        >

                            {{ $insumoId
                                ? 'Guardar cambios'
                                : 'Registrar insumo'
                            }}

                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif


    {{-- Modal de historial del insumo --}}
    @if ($mostrarHistorial)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">

                {{-- Encabezado --}}
                <div class="mb-6 flex items-start justify-between gap-4">

                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Historial del insumo
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ $historialInsumoNombre }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarHistorial"
                        class="text-2xl text-gray-400 hover:text-gray-600"
                    >
                        &times;
                    </button>

                </div>

                {{-- Movimientos --}}
                @if ($movimientosHistorial->isEmpty())

                    <div class="rounded-lg bg-gray-50 px-5 py-10 text-center text-sm text-gray-500">
                        Este insumo todavía no tiene movimientos registrados.
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
                                    <th class="px-4 py-3">Responsable</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">

                                @foreach ($movimientosHistorial as $movimiento)

                                    @foreach ($movimiento->detalles as $detalle)

                                        <tr class="hover:bg-gray-50">

                                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                {{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') ?? '—' }}
                                            </td>

                                            <td class="px-4 py-3">

                                                @if ($movimiento->tipo === 'apertura')

                                                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                                        Apertura
                                                    </span>

                                                @elseif ($movimiento->tipo === 'entrada')

                                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                                        Entrada
                                                    </span>

                                                @elseif ($movimiento->tipo === 'salida')

                                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                                        Salida
                                                    </span>

                                                @endif

                                            </td>

                                            <td class="px-4 py-3 font-medium text-gray-800">
                                                {{ number_format((float) $detalle->cantidad, 2, '.', '') }}
                                            </td>

                                            <td class="px-4 py-3 text-gray-600">
                                                {{ number_format((float) $detalle->existencia_anterior, 2, '.', '') }}
                                            </td>

                                            <td class="px-4 py-3 font-semibold text-gray-800">
                                                {{ number_format((float) $detalle->existencia_posterior, 2, '.', '') }}
                                            </td>

                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $movimiento->usuario?->name ?? 'Usuario no disponible' }}
                                            </td>

                                        </tr>

                                    @endforeach

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                    <p class="mt-3 text-xs text-gray-500">
                        Se muestran hasta los últimos 50 movimientos del insumo.
                    </p>

                @endif

                {{-- Cerrar --}}
                <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">

                    <button
                        type="button"
                        wire:click="cerrarHistorial"
                        class="rounded-lg bg-gray-800 px-5 py-2 font-medium text-white hover:bg-gray-900"
                    >
                        Cerrar
                    </button>

                </div>

            </div>

        </div>

    @endif



    {{-- Modal de catálogo rápido --}}
    @if ($mostrarModalCatalogo)

        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-4 flex items-center justify-between">

                    <div>
                        <h3 class="text-lg font-bold text-gray-800">
                            @if ($tipoCatalogo === 'categoria')
                                Nueva categoría
                            @elseif ($tipoCatalogo === 'marca')
                                Nueva marca
                            @elseif ($tipoCatalogo === 'unidad_medida')
                                Nueva unidad de medida
                            @else
                                Nuevo registro
                            @endif
                        </h3>

                        <p class="text-xs text-gray-500">
                            Ingrese los datos para registrarlo rápidamente.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarModalCatalogo"
                        class="text-2xl text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>

                </div>

                <form
                    wire:submit="guardarCatalogoRapido"
                    class="space-y-4"
                >

                    @if ($tipoCatalogo === 'unidad_medida')

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nombre en singular *
                            </label>

                            <input
                                type="text"
                                wire:model="nuevoNombreCatalogo"
                                placeholder="Ej. Pieza, Metro, Litro"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                                autofocus
                            >

                            @error('nuevoNombreCatalogo')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nombre en plural *
                            </label>

                            <input
                                type="text"
                                wire:model="nuevoNombrePluralUnidad"
                                placeholder="Ej. Piezas, Metros, Litros"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            >

                            @error('nuevoNombrePluralUnidad')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Abreviatura
                            </label>

                            <input
                                type="text"
                                wire:model="nuevaAbreviaturaUnidad"
                                placeholder="Ej. pza, m, l"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            >

                            @error('nuevaAbreviaturaUnidad')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <input
                                id="nuevoPermiteDecimales"
                                type="checkbox"
                                wire:model="nuevoPermiteDecimalesUnidad"
                                class="rounded border-gray-300"
                            >
                            <label for="nuevoPermiteDecimales" class="ml-2 text-sm text-gray-700">
                                Permite cantidades decimales
                            </label>
                        </div>

                    @else

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>

                            <input
                                type="text"
                                wire:model="nuevoNombreCatalogo"
                                placeholder="{{ $tipoCatalogo === 'categoria' ? 'Ej. Redes, Conectividad' : 'Ej. Cisco, Panduit' }}"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                                autofocus
                            >

                            @error('nuevoNombreCatalogo')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                    @endif

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            wire:click="cerrarModalCatalogo"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Guardar
                        </button>
                    </div>

                </form>

            </div>

        </div>

    @endif

</div>