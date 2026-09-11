<x-layouts.app title="Dashboard">

    <div class="space-y-6">

        {{-- Tarjeta de bienvenida --}}
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-800">
                Dashboard
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Bienvenido al sistema, <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span>.
            </p>
        </div>

        {{-- Accesos rápidos --}}
        <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-700">
                Accesos rápidos
            </h2>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Categorías --}}
                <a
                    href="{{ route('catalogos.categorias') }}"
                    class="group block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-blue-50 p-3 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-blue-600 group-hover:translate-x-1 transition-transform inline-flex items-center">
                            Ir &rarr;
                        </span>
                    </div>

                    <h3 class="mt-4 font-semibold text-gray-800 group-hover:text-blue-600 transition">
                        Categorías
                    </h3>

                    <p class="mt-1 text-sm text-gray-500">
                        Gestión y configuración de categorías de inventario.
                    </p>
                </a>

                {{-- Tipos de equipo --}}
                <a
                    href="{{ route('catalogos.tipos-equipo') }}"
                    class="group block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-indigo-50 p-3 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-indigo-600 group-hover:translate-x-1 transition-transform inline-flex items-center">
                            Ir &rarr;
                        </span>
                    </div>

                    <h3 class="mt-4 font-semibold text-gray-800 group-hover:text-indigo-600 transition">
                        Tipos de equipo
                    </h3>

                    <p class="mt-1 text-sm text-gray-500">
                        Catálogo de tipos de equipos tecnológicos.
                    </p>
                </a>

                {{-- Usuarios (Solo Administrador) --}}
                @role('Administrador')
                    <a
                        href="{{ route('administracion.usuarios') }}"
                        class="group block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md"
                    >
                        <div class="flex items-center justify-between">
                            <div class="rounded-lg bg-emerald-50 p-3 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-emerald-600 group-hover:translate-x-1 transition-transform inline-flex items-center">
                                Ir &rarr;
                            </span>
                        </div>

                        <h3 class="mt-4 font-semibold text-gray-800 group-hover:text-emerald-600 transition">
                            Usuarios
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Administración de cuentas y roles de usuarios.
                        </p>
                    </a>
                @endrole

            </div>
        </div>

    </div>

</x-layouts.app>