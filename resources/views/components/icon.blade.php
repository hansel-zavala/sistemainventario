@props(['name'])

@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v4h-7z M14 11h7v10h-7z M3 14h7v7H3z',
        'inventory' => 'm3 7 9-4 9 4-9 4-9-4v10l9 4 9-4V7 M12 11v10 M7.5 5l9 4',
        'monitor' => 'M4 3h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1 M8 21h8 M12 17v4 M3 13h18',
        'tools' => 'M14 6a5 5 0 0 0-6 6l-5 5a2.1 2.1 0 0 0 3 3l5-5a5 5 0 0 0 6-6l-3 3-3-3 3-3 M16 3l5 5 M18 6l-3 3',
        'box' => 'M3 7h18v14H3z M2 3h20v4H2z M9 11h6',
        'drop' => 'M12 3S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12-7-12 M9 16a3 3 0 0 0 3 3',
        'refill' => 'M12 3s-5 6-5 9a5 5 0 0 0 10 0c0-3-5-9-5-9 M3 14a9 9 0 0 0 16 6 M19 16v4h-4',
        'movements' => 'M3 7h17 M16 3l4 4-4 4 M21 17H4 M8 13l-4 4 4 4',
        'entry' => 'M14 4h6v16h-6 M3 12h12 M10 7l5 5-5 5',
        'exit' => 'M10 4H4v16h6 M9 12h12 M16 7l5 5-5 5',
        'loan' => 'M3 3h12v7 M3 3v18h12v-6 M7 7h4 M7 11h2 M12 13h9 M17 9l4 4-4 4',
        'return' => 'M9 4H4v16h16V9 M9 10l4-4 4 4 M13 6v8',
        'catalog' => 'M3 4h7a2 2 0 0 1 2 2v15a3 3 0 0 0-3-2H3z M21 4h-7a2 2 0 0 0-2 2v15a3 3 0 0 1 3-2h6z',
        'tag' => 'M3 3h8l10 10-8 8L3 11z M7 7h.01',
        'devices' => 'M3 4h14v11H3z M7 19h4 M9 15v4 M17 9h5v12h-5z',
        'brand' => 'm12 3 3 2 4 1 1 4 1 3-3 3-1 4-5-1-4 1-1-4-3-3 1-3 1-4 4-1z M8 11l3 3 5-5',
        'building' => 'M4 21V3h12v18 M16 9h4v12 M2 21h20 M8 7h4 M8 11h4 M8 15h4 M8 21v-3h4v3',
        'ruler' => 'm3 16 13-13 5 5L8 21z M13 6l2 2 M10 9l2 2 M7 12l2 2 M4 15l2 2',
        'history' => 'M3 11a9 9 0 1 1 2 7 M3 4v7h7 M12 7v5l3 2',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M8 12l3 3 5-6',
        'users' => 'M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M2 21v-2a7 7 0 0 1 14 0v2 M17 4a4 4 0 0 1 0 8 M18 16a5 5 0 0 1 4 5',
        'user' => 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M4 21v-2a8 8 0 0 1 16 0v2',
        'audit' => 'M14 3H5v18h14V8z M14 3v5h5 M8 12h8 M8 16h5',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'm6 6 12 12 M6 18 18 6',
        'chevron' => 'm9 5 7 7-7 7',
        'search' => 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14 M15 15l6 6',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
        'lock' => 'M5 10h14v11H5z M8 10V6a4 4 0 0 1 8 0v4 M12 14v3',
        'edit' => 'm15 4 5 5 M4 16 17 3l4 4L8 20l-5 1z',
        'check' => 'm5 12 4 4L19 6',
    ];
@endphp

<svg {{ $attributes->class(['app-icon'])->merge(['aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    <path d="{{ $paths[$name] ?? $paths['box'] }}" />
</svg>
