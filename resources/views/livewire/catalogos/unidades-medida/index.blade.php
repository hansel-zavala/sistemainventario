<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Unidades de medida
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de unidades utilizadas en herramientas e insumos.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearUnidadMedida"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nueva unidad
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
                    placeholder="Singular, plural o abreviatura..."
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
                        Activas
                    </option>

                    <option value="inactivo">
                        Inactivas
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
                            Singular
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Plural
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Abreviatura
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Decimales
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

                    @forelse ($unidades as $unidad)

                        <tr class="hover:bg-gray-50">

                            <td class="px-6 py-4 font-medium text-gray-800">
                                {{ $unidad->nombre_singular }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $unidad->nombre_plural }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $unidad->abreviatura ?? '—' }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $unidad->permite_decimales ? 'Sí' : 'No' }}
                            </td>

                            <td class="px-6 py-4">

                                @if ($unidad->activo)

                                    <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                        Activa
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        Inactiva
                                    </span>

                                @endif

                            </td>

                            <td class="px-6 py-4 text-right">

                                <div class="flex justify-end gap-3">

                                    <button
                                        type="button"
                                        wire:click="editarUnidadMedida({{ $unidad->id }})"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $unidad->id }})"
                                        class="{{ $unidad->activo
                                            ? 'font-medium text-red-600 hover:text-red-800'
                                            : 'font-medium text-green-600 hover:text-green-800'
                                        }}"
                                    >
                                        {{ $unidad->activo
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
                                colspan="6"
                                class="px-6 py-10 text-center text-gray-500"
                            >
                                No se encontraron unidades de medida.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $unidades->links() }}
        </div>

    </div>


    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $unidadMedidaId
                                ? 'Editar unidad de medida'
                                : 'Nueva unidad de medida'
                            }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Complete la información de la unidad.
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
                    wire:submit="guardarUnidadMedida"
                    class="space-y-4"
                >

                    <div>

                        <label
                            for="nombreSingular"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre singular *
                        </label>

                        <input
                            id="nombreSingular"
                            type="text"
                            wire:model="nombreSingular"
                            placeholder="Ej. unidad"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombreSingular')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="nombrePlural"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre plural *
                        </label>

                        <input
                            id="nombrePlural"
                            type="text"
                            wire:model="nombrePlural"
                            placeholder="Ej. unidades"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('nombrePlural')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div>

                        <label
                            for="abreviatura"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Abreviatura
                        </label>

                        <input
                            id="abreviatura"
                            type="text"
                            wire:model="abreviatura"
                            placeholder="Ej. und"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        <p class="mt-1 text-xs text-gray-500">
                            Este campo es opcional.
                        </p>

                        @error('abreviatura')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    <div class="space-y-3">

                        <div class="flex items-center">

                            <input
                                id="permiteDecimales"
                                type="checkbox"
                                wire:model="permiteDecimales"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="permiteDecimales"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Permite cantidades decimales
                            </label>

                        </div>


                        <div class="flex items-center">

                            <input
                                id="activoUnidad"
                                type="checkbox"
                                wire:model="activoUnidad"
                                class="rounded border-gray-300"
                            >

                            <label
                                for="activoUnidad"
                                class="ml-2 text-sm text-gray-700"
                            >
                                Unidad de medida activa
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
                            {{ $unidadMedidaId
                                ? 'Guardar cambios'
                                : 'Crear unidad'
                            }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>