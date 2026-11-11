

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


    @if (session()->has('mensaje'))

            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">

                {{ session('mensaje') }}

            </div>

    @endif


    @php
        $filterFields = [
            'rol' => ['label' => 'Rol', 'options' => ['Administrador' => 'Administrador', 'Técnico' => 'Técnico'], 'value' => $rol],
            'estado' => ['label' => 'Estado', 'options' => ['activo' => 'Activos', 'inactivo' => 'Inactivos'], 'value' => $estado],
        ];
        $activeFilters = array_filter($filterFields, fn ($field) => isset($field['options'][$field['value']]));
    @endphp

    <x-data-table
        id="usuarios"
        label="Tabla de usuarios"
        :columns="\App\Services\UsuariosTable::COLUMNS"
        :visible-columns="$visibleColumns"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        search-label="Buscar usuario"
        search-placeholder="Nombre o correo..."
        default-order-label="orden por nombre"
        :collapsible-filters="true"
        :active-filter-count="count($activeFilters)"
    >
        <x-slot:export>
            <x-table-export id="usuarios" :action="route('administracion.usuarios.exportar')"
                :buscar="$buscar" :sort-column="$sortColumn" :sort-direction="$sortDirection"
                :visible-columns="$visibleColumns" :export-filters="['estado' => $estado, 'rol' => $rol]" />
        </x-slot:export>
        <x-slot:filters>
            @foreach ($filterFields as $name => $field)
                <label wire:key="usuarios-filter-{{ $name }}" class="text-sm font-medium text-muted">
                    {{ $field['label'] }}
                    <select wire:model.live="{{ $name }}" class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                        <option value="">Todos</option>
                        @foreach ($field['options'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:summary>
            Mostrando {{ $usuarios->count() }} de {{ $usuarios->total() }} usuarios encontrados
        </x-slot:summary>

        <x-slot:filterChips>
            @foreach ($activeFilters as $name => $field)
                @php
                    $chipLabel = $field['label'].': '.$field['options'][$field['value']];
                @endphp
                <button type="button" wire:key="usuarios-filter-chip-{{ $name }}" wire:click="removeFilter('{{ $name }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $chipLabel }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $chipLabel }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

        @forelse ($usuarios as $usuario)
            <tr wire:key="usuarios-row-{{ $usuario->id }}" class="hover:bg-canvas/60">
                @foreach (\App\Services\UsuariosTable::values($usuario) as $column => $value)
                    @continue(! in_array($column, $visibleColumns, true))
                    <td wire:key="usuarios-cell-{{ $usuario->id }}-{{ $column }}" class="px-4 py-3 text-ink">
                        @if ($column === 'activo')
                            <span @class(['rounded-full px-3 py-1 text-xs font-medium', 'bg-green-50 text-green-700' => $usuario->activo, 'bg-red-50 text-red-700' => ! $usuario->activo])>
                                {{ $value }}
                            </span>
                        @elseif ($column === 'rol')
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $value }}</span>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right">
                    <div class="flex justify-end gap-1">
                        <button type="button" wire:click="editarUsuario({{ $usuario->id }})" class="icon-button text-blue-600"
                            title="Editar usuario" aria-label="Editar usuario {{ $usuario->name }}"><x-icon name="edit" /></button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-muted">No se encontraron usuarios registrados.</td></tr>
        @endforelse

        <x-slot:pagination>
            <x-table-pagination :paginator="$usuarios" label="Paginación de usuarios" />
        </x-slot:pagination>
    </x-data-table>

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