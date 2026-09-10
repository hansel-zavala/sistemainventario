

<div>

    <div class="mb-6 flex items-center justify-between">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Usuarios
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Administración de usuarios del sistema.
            </p>
        </div>

        <button
            type="button"
            wire:click="crearUsuario"
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
        >
            + Nuevo usuario
        </button>

    </div>


    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Buscar
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Nombre o correo..."
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
            </div>


            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Rol
                </label>

                <select
                    wire:model.live="rol"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2"
                >
                    <option value="">
                        Todos
                    </option>

                    <option value="Administrador">
                        Administrador
                    </option>

                    <option value="Técnico">
                        Técnico
                    </option>
                </select>
            </div>


            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Estado
                </label>

                <select
                    wire:model.live="estado"
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

    @if (session()->has('mensaje'))

            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">

                {{ session('mensaje') }}

            </div>

    @endif


    <div class="overflow-hidden rounded-xl bg-white shadow-sm">


        <div class="overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-50">
                    <tr>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Usuario
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Correo
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                            Rol
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

                    @forelse ($usuarios as $usuario)

                        <tr class="hover:bg-gray-50">

                            <td class="px-6 py-4 font-medium text-gray-800">
                                {{ $usuario->name }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ $usuario->email }}
                            </td>

                            <td class="px-6 py-4">

                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                    {{ $usuario->getRoleNames()->first() ?? 'Sin rol' }}
                                </span>

                            </td>

                            <td class="px-6 py-4">

                                @if ($usuario->activo)

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

                                <button
                                    type="button"
                                    wire:click="editarUsuario({{ $usuario->id }})"
                                    class="font-medium text-blue-600 hover:text-blue-800"
                                >
                                    Editar
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="5"
                                class="px-6 py-10 text-center text-gray-500"
                            >
                                No se encontraron usuarios.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        <div class="border-t border-gray-200 px-6 py-4">
            {{ $usuarios->links() }}
        </div>

    </div>

    @if ($mostrarModal)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">

            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">

                {{-- Encabezado --}}
                <div class="mb-6 flex items-center justify-between">

                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            {{ $usuarioId ? 'Editar usuario' : 'Nuevo usuario' }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ $usuarioId
                                ? 'Actualice la información del usuario.'
                                : 'Complete la información del nuevo usuario.'
                            }}
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


                <form wire:submit="guardarUsuario" class="space-y-4">

                    {{-- Nombre --}}
                    <div>

                        <label
                            for="name"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Nombre completo *
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('name')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Correo --}}
                    <div>

                        <label
                            for="email"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Correo electrónico *
                        </label>

                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @error('email')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Rol --}}
                    <div>

                        <label
                            for="rolUsuario"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Rol *
                        </label>

                        <select
                            id="rolUsuario"
                            wire:model="rolUsuario"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                            <option value="Técnico">
                                Técnico
                            </option>

                            <option value="Administrador">
                                Administrador
                            </option>

                        </select>

                        @error('rolUsuario')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Contraseña --}}
                    <div>

                        <label
                            for="password"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Contraseña {{ $usuarioId ? '' : '*' }}
                        </label>

                        <input
                            id="password"
                            type="password"
                            wire:model="password"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                        @if ($usuarioId)
                            <p class="mt-1 text-xs text-gray-500">
                                Déjela vacía si no desea cambiar la contraseña.
                            </p>
                        @endif

                        @error('password')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>


                    {{-- Confirmación --}}
                    <div>

                        <label
                            for="password_confirmation"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Confirmar contraseña {{ $usuarioId ? '' : '*' }}
                        </label>

                        <input
                            id="password_confirmation"
                            type="password"
                            wire:model="password_confirmation"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2"
                        >

                    </div>


                    {{-- Estado --}}
                    <div class="flex items-center">

                        <input
                            id="activoUsuario"
                            type="checkbox"
                            wire:model="activoUsuario"
                            class="rounded border-gray-300"
                        >

                        <label
                            for="activoUsuario"
                            class="ml-2 text-sm text-gray-700"
                        >
                            Usuario activo
                        </label>

                    </div>

                    @error('activoUsuario')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror


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
                            {{ $usuarioId ? 'Guardar cambios' : 'Crear usuario' }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    @endif

</div>