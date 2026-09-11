<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Departamentos
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de departamentos de procedencia.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearDepartamento"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo departamento
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
                    placeholder="Nombre o sigla..."
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

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Departamento
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Sigla
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Creado por
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Estado
                        </th>

                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-200">

                    @forelse ($departamentos as $departamento)

                        <tr class="hover:bg-gray-50">

                            <td class="px-6 py-4 font-medium text-gray-800">
                                {{ $departamento->nombre }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $departamento->sigla ?? '—' }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $departamento->creador?->name ?? 'No disponible' }}
                            </td>

                            <td class="px-6 py-4">

                                @if ($departamento->activo)

                                    <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                        Activo
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        Inactivo
                                    </span>

                                @endif

                            </td>

                            <td class="px-6 py-4 text-right">

                                <div class="flex justify-end gap-3">

                                    <button
                                        type="button"
                                        wire:click="editarDepartamento({{ $departamento->id }})"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $departamento->id }})"
                                        class="{{ $departamento->activo
                                            ? 'font-medium text-red-600 hover:text-red-800'
                                            : 'font-medium text-green-600 hover:text-green-800'
                                        }}"
                                    >
                                        {{ $departamento->activo
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
                                colspan="5"
                                class="px-6 py-10 text-center text-gray-500"
                            >
                                No se encontraron departamentos.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $departamentos->links() }}
        </div>

    </div>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $departamentoId
                                ? 'Editar departamento'
                                : 'Nuevo departamento'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información del departamento.
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
                    wire:submit="guardarDepartamento"
                    class="space-y-4"
                >

                    <div>

                        <label
                            for="nombre"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre *
                        </label>

                        <input
                            id="nombre"
                            type="text"
                            wire:model="nombre"
                            placeholder="Ej. Recursos Humanos"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="sigla"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Sigla
                        </label>

                        <input
                            id="sigla"
                            type="text"
                            wire:model="sigla"
                            placeholder="Ej. RRHH"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Este campo es opcional.
                        </p>

                        @error('sigla')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <div class="flex items-center">

                            <input
                                id="activoDepartamento"
                                type="checkbox"
                                wire:model="activoDepartamento"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoDepartamento"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Departamento activo
                            </label>

                        </div>

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
                            {{ $departamentoId
                                ? 'Guardar cambios'
                                : 'Crear departamento'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>