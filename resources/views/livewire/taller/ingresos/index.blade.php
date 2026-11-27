<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Ingresos de Taller</h1>
            <p class="mt-1 text-sm text-muted">Recepción de equipos para revisión, mantenimiento y otros servicios técnicos.</p>
        </div>
        <a href="{{ route('taller.ingresos.nuevo') }}" class="primary-button"><x-icon name="plus" class="size-4" /> Nuevo ingreso</a>
    </header>

    <section aria-label="Ingresos registrados" class="rounded-xl border border-line bg-surface">
        <div class="border-b border-line px-4 py-4 sm:px-5">
            <label for="buscar-ingreso" class="mb-1 block text-sm font-medium text-ink">Buscar por folio o número de inventario</label>
            <input id="buscar-ingreso" type="search" wire:model.live.debounce.300ms="buscar" maxlength="100"
                placeholder="Ej. T-0001" class="profile-input max-w-md">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[43rem] text-left text-sm">
                <thead class="bg-canvas text-muted">
                    <tr>
                        @foreach (['Folio', 'Equipo', 'Departamento', 'Fecha de ingreso', 'Estado', 'Acción'] as $columna)
                            <th scope="col" class="px-4 py-3 font-semibold">{{ $columna }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($ingresos as $ingreso)
                        <tr wire:key="ingreso-{{ $ingreso->id }}">
                            <th scope="row" class="whitespace-nowrap px-4 py-3 font-semibold">{{ $ingreso->folio ?? 'Por emitir' }}</th>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $ingreso->tipo_equipo ?? 'Por identificar' }} {{ $ingreso->marca }}</div>
                                @if ($ingreso->numero_inventario)
                                    <div class="mt-0.5 text-xs text-muted">{{ $ingreso->numero_inventario }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $ingreso->departamento_nombre ?? 'Por indicar' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $ingreso->fecha_ingreso?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
                            <td class="px-4 py-3">
                                <span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-800' => $ingreso->firmado(), 'bg-amber-50 text-amber-800' => $ingreso->emitido() && ! $ingreso->firmado(), 'bg-canvas text-muted' => ! $ingreso->emitido()])>{{ $ingreso->estado() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('taller.ingresos.ver', $ingreso) }}" class="font-semibold text-accent hover:underline">
                                    {{ $ingreso->emitido() ? 'Ver ingreso' : 'Continuar borrador' }}<span class="sr-only"> {{ $ingreso->folio ?? $ingreso->id }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-muted">{{ filled(trim($buscar)) ? 'No se encontraron ingresos con esa búsqueda.' : 'Todavía no hay ingresos de Taller.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($ingresos->hasPages())
            <div class="border-t border-line px-4 py-3"><x-table-pagination :paginator="$ingresos" label="Paginación de ingresos de Taller" /></div>
        @endif
    </section>
</div>
