<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Equipos tecnológicos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Registro y control de los equipos tecnológicos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearEquipo"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo equipo
        </button>

    </div>


    @if (session()->has('mensaje'))

        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('mensaje') }}
        </div>

    @endif


    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <label class="mb-1 block text-sm font-medium text-gray-700">
            Buscar equipo
        </label>

        <input
            type="text"
            wire:model.live.debounce.300ms="buscar"
            placeholder="Inventario, serie, marca, modelo o tipo..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2"
        >

    </div>


    <div class="overflow-hidden rounded-xl bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Inventario
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Equipo
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Marca / Modelo
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Serie
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Procedencia
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">

                    @forelse ($equipos as $equipo)

                        <tr class="hover:bg-gray-50">

                            <td class="px-5 py-4 font-medium text-gray-800">
                                {{ $equipo->numero_inventario }}
                            </td>


                            <td class="px-5 py-4">

                                <div class="font-medium text-gray-800">
                                    {{ $equipo->tipoEquipo->nombre }}
                                </div>

                                <div class="text-sm text-gray-500">
                                    {{ $equipo->categoria->nombre }}
                                </div>

                            </td>


                            <td class="px-5 py-4 text-gray-600">

                                {{ $equipo->marca->nombre }}

                                @if ($equipo->modelo)
                                    / {{ $equipo->modelo }}
                                @endif

                            </td>


                            <td class="px-5 py-4 text-gray-600">
                                {{ $equipo->numero_serie ?? '—' }}
                            </td>


                            <td class="px-5 py-4 text-gray-600">

                                {{ $equipo->departamento->nombre }}

                                @if ($equipo->departamento->sigla)
                                    ({{ $equipo->departamento->sigla }})
                                @endif

                            </td>


                            <td class="px-5 py-4 text-right">

                                <button
                                    type="button"
                                    wire:click="editarEquipo({{ $equipo->id }})"
                                    class="font-medium text-blue-600 hover:text-blue-800"
                                >
                                    Editar
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                No se encontraron equipos registrados.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $equipos->links() }}
        </div>

    </div>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-8">

            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $equipoId
                                ? 'Editar equipo'
                                : 'Nuevo equipo'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información básica del equipo.
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
                    wire:submit="guardarEquipo"
                    class="space-y-5"
                >

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">


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


                        {{-- Tipo --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Tipo de equipo *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="tipoEquipoId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
                                    </option>

                                    @foreach ($tiposEquipo as $tipo)
                                        <option value="{{ $tipo->id }}">
                                            {{ $tipo->nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('tipo_equipo')"
                                    title="Nuevo tipo de equipo"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('tipoEquipoId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Departamento --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Departamento de procedencia *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="departamentoId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
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

                                <button
                                    type="button"
                                    wire:click="abrirModalCatalogo('departamento')"
                                    title="Nuevo departamento"
                                    class="inline-flex items-center justify-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3.5 text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors focus:outline-none"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>

                            @error('departamentoId')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Marca --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Marca *
                            </label>

                            <div class="flex">
                                <select
                                    wire:model="marcaId"
                                    class="w-full rounded-l-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">
                                        Seleccione...
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


                        {{-- Modelo --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Modelo
                            </label>

                            <input
                                type="text"
                                wire:model="modelo"
                                placeholder="Ej. Latitude 5420"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('modelo')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Color --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Color
                            </label>

                            <input
                                type="text"
                                wire:model="color"
                                placeholder="Ej. Negro"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('color')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Serie --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Número de serie
                            </label>

                            <input
                                type="text"
                                wire:model="numeroSerie"
                                placeholder="Serie del fabricante"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('numeroSerie')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Inventario --}}
                        <div>

                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Número de inventario *
                            </label>

                            <input
                                type="text"
                                wire:model="numeroInventario"
                                placeholder="Número de inventario municipal"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2"
                            >

                            @error('numeroInventario')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                    </div>


                    {{-- Observaciones --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="4"
                            placeholder="Observaciones adicionales del equipo..."
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
                            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        >
                            {{ $equipoId
                                ? 'Guardar cambios'
                                : 'Registrar equipo'
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
                            @elseif ($tipoCatalogo === 'tipo_equipo')
                                Nuevo tipo de equipo
                            @elseif ($tipoCatalogo === 'departamento')
                                Nuevo departamento
                            @elseif ($tipoCatalogo === 'marca')
                                Nueva marca
                            @else
                                Nuevo registro
                            @endif
                        </h3>

                        <p class="text-xs text-gray-500">
                            Ingrese el nombre para registrarlo rápidamente.
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

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Nombre *
                        </label>

                        <input
                            type="text"
                            wire:model="nuevoNombreCatalogo"
                            placeholder="Nombre del registro"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            autofocus
                        >

                        @error('nuevoNombreCatalogo')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    @if ($tipoCatalogo === 'departamento')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Sigla
                            </label>

                            <input
                                type="text"
                                wire:model="nuevaSiglaDepartamento"
                                placeholder="Ej. RRHH"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none"
                            >

                            @error('nuevaSiglaDepartamento')
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