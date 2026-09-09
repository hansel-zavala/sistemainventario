<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100 min-h-screen">

    <div class="max-w-6xl mx-auto px-6 py-8">

        <div class="bg-white rounded-xl shadow p-6">

            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        Dashboard
                    </h1>

                    <p class="text-gray-500 mt-1">
                        Bienvenido, {{ auth()->user()->name }}
                    </p>

                    <p class="text-sm text-gray-500 mt-1">
                        Rol:
                        {{ auth()->user()->getRoleNames()->first() }}
                    </p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700"
                    >
                        Cerrar sesión
                    </button>
                </form>
            </div>

        </div>

    </div>

</body>
</html>