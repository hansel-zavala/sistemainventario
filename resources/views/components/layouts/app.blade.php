@php
    $navigation = [
        ['label' => 'Inventario', 'icon' => 'inventory', 'items' => [
            ['inventario.equipos', 'Equipos tecnológicos', 'monitor'],
            ['inventario.herramientas', 'Herramientas', 'tools'],
            ['inventario.insumos', 'Insumos', 'box'],
            ['inventario.tintas', 'Tinta', 'drop'],
            ['inventario.tintas.recargas', 'Recargas de tinta', 'refill'],
        ]],
        ['label' => 'Movimientos', 'icon' => 'movements', 'items' => [
            ['movimientos.entradas', 'Entradas', 'entry'],
            ['movimientos.salidas', 'Salidas', 'exit'],
            ['movimientos.prestamos', 'Préstamos', 'loan'],
            ['movimientos.devoluciones', 'Devoluciones', 'return'],
        ]],
        ['label' => 'Catálogos', 'icon' => 'catalog', 'items' => [
            ['catalogos.categorias', 'Categorías', 'tag'],
            ['catalogos.tipos-equipo', 'Tipos de equipo', 'devices'],
            ['catalogos.marcas', 'Marcas', 'brand'],
            ['catalogos.departamentos', 'Departamentos', 'building'],
            ['catalogos.unidades-medida', 'Unidades de medida', 'ruler'],
        ]],
        ['label' => 'Historial', 'icon' => 'history', 'items' => [
            ['historial.movimientos', 'Historial de movimientos', 'history'],
        ]],
    ];

    if (auth()->user()->hasRole('Administrador')) {
        $navigation[] = ['label' => 'Administración', 'icon' => 'shield', 'items' => [
            ['administracion.usuarios', 'Usuarios', 'users'],
            ['administracion.auditorias', 'Auditoría del sistema', 'audit'],
        ]];
    }

    $currentSection = null;
    $currentPage = request()->routeIs('perfil.*') ? 'Mi perfil' : ($title ?? 'Dashboard');
    foreach ($navigation as $group) {
        foreach ($group['items'] as [$route, $label]) {
            if (request()->routeIs($route)) {
                $currentSection = $group['label'];
                $currentPage = $label;
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? $currentPage }} | Sistema de Inventario</title>
    <link rel="icon" href="{{ asset('image/LogoLaCeibaPNG.png') }}">
    <script>
        try {
            document.documentElement.dataset.sidebarCompact = localStorage.getItem('inventory.sidebar.{{ auth()->id() }}.compact') === 'true' ? 'true' : 'false';
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="app-shell min-h-dvh bg-canvas text-ink antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-surface focus:p-3 focus:text-accent">Saltar al contenido</a>
    <div data-app-navigation data-user-id="{{ auth()->id() }}">
        <button type="button" data-sidebar-backdrop hidden tabindex="-1" aria-label="Cerrar menú" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"></button>
        <x-layouts.sidebar :navigation="$navigation" />
        <div data-page-content class="app-content min-w-0">
            <x-layouts.topbar :current-section="$currentSection" :current-page="$currentPage" />
            <main id="main-content" tabindex="-1" class="min-w-0 p-4 outline-none sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
        <div data-navigation-tooltip hidden role="tooltip" class="pointer-events-none fixed z-[70] max-w-64 rounded-lg bg-ink px-3 py-2 text-xs font-medium text-white shadow-lg"></div>
    </div>
    @livewireScripts
</body>
</html>
