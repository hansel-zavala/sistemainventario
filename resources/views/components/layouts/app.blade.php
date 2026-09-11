<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $title ?? 'Sistema de Inventario' }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen bg-gray-100">

    <div class="min-h-screen flex">

        <aside class="w-64 bg-gray-900 text-white min-h-screen">

            <div class="px-6 py-6 border-b border-gray-800">

                <h1 class="font-bold text-lg">
                    Sistema de Inventario
                </h1>

                <p class="text-sm text-gray-400 mt-1">
                    Departamento de Informática
                </p>

            </div>

            <nav class="p-4 space-y-2">

                <a
                    href="{{ route('dashboard') }}"
                    class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                >
                    Dashboard
                </a>

                <div class="pt-3">

                    <p class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        Catálogos
                    </p>

                    <a
                        href="{{ route('catalogos.categorias') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Categorías
                    </a>

                    <a
                        href="{{ route('catalogos.tipos-equipo') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Tipos de equipo
                    </a>

                    <a
                        href="{{ route('catalogos.marcas') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Marcas
                    </a>

                    <a
                        href="{{ route('catalogos.departamentos') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Departamentos
                    </a>

                    <a
                        href="{{ route('catalogos.unidades-medida') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Unidades de medida
                    </a>

                </div>

                @role('Administrador')

                    <a
                        href="{{ route('administracion.usuarios') }}"
                        class="block rounded-lg px-4 py-2 hover:bg-gray-800"
                    >
                        Usuarios
                    </a>

                @endrole

            </nav>

        </aside>


        <div class="flex-1">

            <header class="bg-white border-b border-gray-200 px-8 py-4">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="font-semibold text-gray-800">
                            {{ auth()->user()->name }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ auth()->user()->getRoleNames()->first() }}
                        </p>

                    </div>

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            Cerrar sesión
                        </button>

                    </form>

                </div>

            </header>


            <main class="p-8">

                {{ $slot }}

            </main>

        </div>

    </div>

    @livewireScripts
</body>
</html>