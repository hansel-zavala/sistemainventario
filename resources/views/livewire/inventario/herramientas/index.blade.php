<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Herramientas
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de herramientas del Departamento de Informática.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearHerramienta"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva herramienta
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    {{-- Filtros --}}
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Nombre, código o marca..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >

            </div>


            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Tipo de control
                </label>

                <select
                    wire:model.live="filtroTipo"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
                    <option value="">Todos</option>
                    <option value="individual">Individual</option>
                    <option value="cantidad">Por cantidad</option>
                </select>

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


    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Herramienta
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Categoría
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Control
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Código
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

                    @forelse ($herramientas as $herramienta)

                        <tr class="hover:bg-gray-50">

                            <td class="px-5 py-4">

                                <div class="font-medium text-gray-800">
                                    {{ $herramienta->nombre }}
                                </div>

                                @if ($herramienta->marca)

                                    <div class="text-sm text-gray-500">
                                        {{ $herramienta->marca->nombre }}
                                    </div>

                                @endif

                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $herramienta->categoria->nombre }}
                            </td>


                            <td class="px-5 py-4 text-gray-600">

                                {{ $herramienta->tipo_control === 'individual'
                                    ? 'Individual'
                                    : 'Por cantidad'
                                }}

                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $herramienta->codigo_interno ?? '—' }}
                            </td>


                            <td class="px-5 py-4">

                                @php
                                    $cantidad = (float) $herramienta->cantidad_actual;

                                    $nombreUnidad =
                                        $cantidad == 1
                                            ? $herramienta->unidadMedida->nombre_singular
                                            : $herramienta->unidadMedida->nombre_plural;
                                @endphp

                                <span
                                    class="{{ $herramienta->tipo_control === 'cantidad'
                                        && $cantidad <= (float) $herramienta->stock_minimo
                                            ? 'font-semibold text-red-600'
                                            : 'text-gray-700'
                                    }}"
                                >
                                    {{ rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.') }}
                                    {{ $nombreUnidad }}
                                </span>


                                @if (
                                    $herramienta->tipo_control === 'cantidad'
                                    && $cantidad <= (float) $herramienta->stock_minimo
                                )

                                    <div class="mt-1 text-xs text-red-600">
                                        Stock bajo
                                    </div>

                                @endif

                            </td>


                            <td class="px-5 py-4">

                                @if ($herramienta->activo)

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
                                        wire:click="editarHerramienta({{ $herramienta->id }})"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $herramienta->id }})"
                                        class="{{ $herramienta->activo
                                            ? 'font-medium text-red-600 hover:text-red-800'
                                            : 'font-medium text-green-600 hover:text-green-800'
                                        }}"
                                    >
                                        {{ $herramienta->activo
                                            ? 'Desactivar'
                                            : 'Activar'
                                        }}
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
                                No se encontraron herramientas registradas.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $herramientas->links() }}
        </div>

    </div>


    {{-- Modal --}}
    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $herramientaId
                                ? 'Editar herramienta'
                                : 'Nueva herramienta'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información de la herramienta.
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
                    wire:submit="guardarHerramienta"
                    class="space-y-5"
                >

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


                        {{-- Nombre --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>

                            <input
                                type="text"
                                wire:model="nombre"
                                placeholder="Ej. Multímetro digital"
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


                        {{-- Tipo control --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Tipo de control *
                            </label>

                            <select
                                wire:model.live="tipoControl"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                                <option value="individual">
                                    Individual
                                </option>

                                <option value="cantidad">
                                    Por cantidad
                                </option>

                            </select>

                            <p class="mt-1 text-xs text-gray-500">

                                @if ($tipoControl === 'individual')

                                    Para herramientas que se controlan una por una.

                                @else

                                    Para herramientas iguales controladas mediante existencias.

                                @endif

                            </p>

                        </div>


                        {{-- Código --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">

                                Código interno

                                @if ($tipoControl === 'individual')
                                    *
                                @endif

                            </label>

                            <input
                                type="text"
                                wire:model="codigoInterno"
                                placeholder="Ej. HER-001"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('codigoInterno')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        @if ($tipoControl === 'cantidad')

                            {{-- Cantidad --}}
                            <div>

                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Cantidad actual *
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model="cantidadActual"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                                >

                                @error('cantidadActual')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            {{-- Stock mínimo --}}
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
                                    Permitirá generar alertas de existencias bajas.
                                </p>

                                @error('stockMinimo')
                                    <p class="mt-1 text-sm text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                        @else

                            <div class="md:col-span-2">

                                <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
                                    Una herramienta individual se registra con existencia de 1 unidad.
                                </div>

                            </div>

                        @endif

                    </div>


                    {{-- Observaciones --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="4"
                            placeholder="Información adicional de la herramienta..."
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        ></textarea>

                        @error('observaciones')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Activa --}}
                    <div class="flex items-center">

                        <input
                            id="activoHerramienta"
                            type="checkbox"
                            wire:model="activoHerramienta"
                            class="rounded border-gray-300"
                        >

                        <label
                            for="activoHerramienta"
                            class="ml-2 text-sm text-gray-700"
                        >
                            Herramienta activa
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

                            {{ $herramientaId
                                ? 'Guardar cambios'
                                : 'Registrar herramienta'
                            }}

                        </button>

                    </div>

                </form>

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
                                placeholder="{{ $tipoCatalogo === 'categoria' ? 'Ej. Manuales, Eléctricas' : 'Ej. Stanley, Bosch' }}"
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