@props(['currentSection', 'currentPage'])

<header class="sticky top-0 z-30 flex h-20 items-center justify-between gap-4 border-b border-line bg-surface px-4 sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3 sm:gap-4">
        <button type="button" data-sidebar-toggle aria-label="Abrir menú" aria-controls="app-sidebar" aria-expanded="false" class="icon-button"><x-icon name="menu" /></button>
        <nav aria-label="Ubicación actual" class="min-w-0">
            <ol class="flex min-w-0 items-center gap-2 text-sm">
                @if ($currentSection)
                    <li class="hidden text-muted sm:block">{{ $currentSection }}</li>
                    <li aria-hidden="true" class="hidden text-muted sm:block"><x-icon name="chevron" class="size-3" /></li>
                @endif
                <li aria-current="page" class="truncate font-semibold text-ink">{{ $currentPage }}</li>
            </ol>
        </nav>
    </div>
    <div class="relative shrink-0" data-notifications>
        <button type="button" data-notifications-toggle aria-label="Notificaciones" aria-controls="notifications-panel" aria-expanded="false" class="icon-button"><x-icon name="bell" /></button>
        <section id="notifications-panel" hidden aria-labelledby="notifications-title" class="absolute top-14 right-0 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-line bg-surface p-5 shadow-lg">
            <h2 id="notifications-title" class="font-semibold text-ink">Notificaciones</h2>
            <div class="flex flex-col items-center gap-3 py-6 text-center">
                <span class="rounded-full bg-canvas p-3 text-muted"><x-icon name="bell" class="size-6" /></span>
                <p class="text-sm leading-6 text-muted">Las notificaciones estarán disponibles próximamente.</p>
            </div>
        </section>
    </div>
</header>
