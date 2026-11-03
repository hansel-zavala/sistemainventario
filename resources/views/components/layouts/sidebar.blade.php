@props(['navigation'])

<aside id="app-sidebar" aria-label="Navegación principal" class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-line bg-surface">
    <div class="flex h-24 shrink-0 items-center gap-3 border-b border-line px-5">
        <a href="{{ route('dashboard') }}" class="shrink-0 rounded-lg" aria-label="Sistema de Inventario, ir al inicio">
            <img src="{{ asset('image/LogoLaCeibaPNG.png') }}" alt="Escudo de La Ceiba" width="40" height="48" class="h-12 w-10 object-contain">
        </a>
        <div class="sidebar-label min-w-0">
            <p class="text-sm font-bold text-ink">Sistema de Inventario</p>
            <p class="mt-1 text-xs text-muted">Municipalidad de La Ceiba</p>
        </div>
        <button type="button" data-sidebar-close class="icon-button ml-auto lg:hidden" aria-label="Cerrar menú"><x-icon name="close" /></button>
    </div>

    <div class="px-4 pt-5 pb-3">
        <label class="sidebar-search flex items-center gap-2 rounded-lg border border-line bg-canvas px-3 text-muted">
            <x-icon name="search" class="size-4" />
            <span class="sr-only">Buscar módulo</span>
            <input data-module-search type="search" placeholder="Buscar módulo…" autocomplete="off" class="h-10 w-full min-w-0 bg-transparent text-sm text-ink outline-none placeholder:text-muted">
        </label>
        <button type="button" data-expand-search data-tooltip="Buscar módulo" class="sidebar-search-compact icon-button" aria-label="Buscar módulo"><x-icon name="search" /></button>
    </div>

    <nav class="sidebar-navigation min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-5" aria-label="Módulos">
        <a data-navigation-link data-tooltip="Dashboard" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif class="nav-link mb-4">
            <x-icon name="dashboard" /><span class="sidebar-label">Dashboard</span>
        </a>
        <div class="flex flex-col gap-1.5">
            @foreach ($navigation as $group)
                @php
                    $groupId = $group['icon'];
                    $active = collect($group['items'])->contains(fn ($item) => request()->routeIs($item[0]));
                @endphp
                <div data-navigation-group data-group-id="{{ $groupId }}" data-active="{{ $active ? 'true' : 'false' }}">
                    <button type="button" data-group-toggle data-tooltip="{{ $group['label'] }}" aria-controls="nav-{{ $groupId }}" aria-expanded="{{ $active ? 'true' : 'false' }}" @class(['nav-link w-full', 'nav-group-active' => $active])>
                        <x-icon :name="$group['icon']" />
                        <span class="sidebar-label flex-1 text-left">{{ $group['label'] }}</span>
                        <x-icon name="chevron" class="sidebar-label nav-chevron size-3.5" />
                    </button>
                    <div id="nav-{{ $groupId }}" data-group-panel @if(! $active) hidden @endif class="nav-group-panel">
                        <p class="nav-flyout-heading px-3 pt-2 pb-3 text-sm font-semibold text-ink">{{ $group['label'] }}</p>
                        <div class="flex flex-col gap-1">
                            @foreach ($group['items'] as [$route, $label, $icon])
                                <a data-navigation-link href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif class="nav-link text-[13px]">
                                    <x-icon :name="$icon" class="size-[18px]" /><span>{{ $label }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p data-search-empty hidden role="status" class="px-3 py-5 text-sm text-muted">No se encontraron módulos.</p>
    </nav>

    <div class="sidebar-account flex shrink-0 items-center gap-1 border-t border-line bg-canvas p-4">
        <a href="{{ route('perfil.show') }}" data-tooltip="Ver perfil" aria-label="Ver perfil" title="Ver perfil" @if(request()->routeIs('perfil.*')) aria-current="page" @endif class="sidebar-profile flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-lg p-1 hover:bg-accent-soft aria-[current=page]:bg-accent-soft">
            <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-bold text-accent">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="sidebar-label min-w-0">
                <p class="truncate text-sm font-semibold" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-muted">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}</p>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="flex shrink-0 justify-center">
            @csrf
            <button type="submit" data-tooltip="Cerrar sesión" aria-label="Cerrar sesión" title="Cerrar sesión" class="icon-button hover:bg-red-50 hover:text-red-700">
                <x-icon name="exit" />
            </button>
        </form>
    </div>
</aside>
