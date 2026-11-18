<div class="space-y-6">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Auditoría del sistema</h1>
            <p class="mt-1 text-sm text-gray-500">
                Consulte las operaciones administrativas, accesos y eventos de seguridad del sistema.
            </p>
        </div>
        <span class="inline-flex w-fit items-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600">
            Registro de solo lectura
        </span>
    </div>

    @php
        /** @var string $filtroModulo */
        /** @var string $filtroAccion */
        /** @var string $filtroUsuario */
        /** @var string $orden */

        $columns = \App\Services\AuditoriasTable::COLUMNS;
        $orders = \App\Services\MovimientosTable::ORDERS;
        $selects = [
            'filtroModulo' => ['Módulo', \App\Services\AuditoriasTable::MODULOS],
            'filtroAccion' => ['Acción', \App\Services\AuditoriasTable::ACCIONES],
            'filtroUsuario' => ['Responsable', $usuarios],
            'orden' => ['Orden por fecha', $orders],
        ];
        $activeFilters = array_filter([
            'filtroModulo' => isset($selects['filtroModulo'][1][$filtroModulo]) ? 'Módulo: '.$selects['filtroModulo'][1][$filtroModulo] : null,
            'filtroAccion' => isset($selects['filtroAccion'][1][$filtroAccion]) ? 'Acción: '.$selects['filtroAccion'][1][$filtroAccion] : null,
            'filtroUsuario' => isset($usuarios[$filtroUsuario]) ? 'Responsable: '.$usuarios[$filtroUsuario] : null,
            'orden' => $orden !== \App\Services\MovimientosTable::DEFAULT_ORDER && isset($orders[$orden]) ? 'Orden: '.$orders[$orden] : null,
            'desde' => $desde !== '' ? 'Desde: '.$desde : null,
            'hasta' => $hasta !== '' ? 'Hasta: '.$hasta : null,
        ]);
    @endphp

    <x-data-table id="auditorias" label="Historial de auditoría" :columns="$columns"
        :show-columns="false" :sortable="false" actions-label="Detalle"
        :collapsible-filters="true" :active-filter-count="count($activeFilters)"
        :export-disabled="$filterErrors->isNotEmpty()" search-label="Buscar en la auditoría"
        search-placeholder="Usuario, módulo, acción o IP...">
        <x-slot:filters>
            @foreach ($selects as $field => [$label, $options])
                <label wire:key="auditoria-select-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <select wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="auditoria-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                        @if ($field !== 'orden')
                            <option value="">Todos</option>
                        @endif
                        @foreach ($options as $value => $option)
                            <option value="{{ $value }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            @foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)
                <label wire:key="auditoria-date-{{ $field }}" class="text-sm font-medium text-muted">{{ $label }}
                    <input type="date" wire:model.live="{{ $field }}" @if ($filterErrors->has($field)) aria-invalid="true" aria-describedby="auditoria-filter-error" @endif
                        class="mt-1 min-h-11 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink">
                </label>
            @endforeach
        </x-slot:filters>
        <x-slot:export>
            <x-table-export id="auditorias" :action="route('administracion.auditorias.exportar')" :buscar="$buscar"
                :visible-columns="array_keys(\App\Services\AuditoriasTable::COLUMNS)" :export-filters="$filters" :show-date-fields="false" />
        </x-slot:export>
        <x-slot:summary>
            @if ($filterErrors->isNotEmpty())
                <span id="auditoria-filter-error" class="text-red-700">{{ $filterErrors->first() }}</span>
            @else
                Mostrando {{ $auditorias->count() }} de {{ $auditorias->total() }} registros encontrados
            @endif
        </x-slot:summary>
        <x-slot:filterChips>
            @foreach ($activeFilters as $filter => $label)
                <button type="button" wire:key="auditoria-chip-{{ $filter }}" wire:click="removeFilter('{{ $filter }}')"
                    wire:loading.attr="disabled" aria-label="Quitar filtro {{ $label }}"
                    class="inline-flex min-h-8 max-w-full cursor-pointer items-center gap-1.5 rounded-lg border border-line bg-canvas px-2.5 py-1 text-left text-sm text-ink hover:bg-accent-soft disabled:opacity-50">
                    <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                    <x-icon name="close" class="size-3.5" />
                </button>
            @endforeach
        </x-slot:filterChips>

                    @forelse ($auditorias as $auditoria)
                        <tr wire:key="auditoria-{{ $auditoria->id }}" class="hover:bg-gray-50">
                            @php
                                $values = \App\Services\AuditoriasTable::values($auditoria);
                            @endphp
                            <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ $values['fecha'] }}</td>
                            <td class="px-5 py-4 text-gray-600">{{ $values['responsable'] }}</td>
                            <td class="px-5 py-4 font-medium text-gray-800">{{ $values['modulo'] }}</td>
                            <td class="px-5 py-4 text-gray-600">{{ $values['registro'] }}</td>

                            <td class="px-5 py-4">
                                @php
                                    $colorAccion = match ($auditoria->accion) {
                                        'creacion', 'activacion' => 'bg-green-100 text-green-800',
                                        'desactivacion' => 'bg-red-100 text-red-800',
                                        'cambio_rol', 'cambio_contrasena', 'contrasena_restablecida' => 'bg-purple-100 text-purple-800',
                                        'inicio_sesion_fallido', 'bloqueo_login', 'sesion_vencida' => 'bg-red-100 text-red-800',
                                        'inicio_sesion_exitoso', 'cierre_sesion' => 'bg-cyan-100 text-cyan-800',
                                        'recuperacion_solicitada', 'codigo_recuperacion_verificado' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-blue-100 text-blue-800',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $colorAccion }}">
                                    {{ $values['accion'] }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" wire:click="verDetalle({{ $auditoria->id }})"
                                        class="whitespace-nowrap rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Ver detalle
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                {{ $filterErrors->isNotEmpty() ? 'Corrija los filtros para ver la auditoría.' : 'No se encontraron registros de auditoría con los filtros seleccionados.' }}
                            </td>
                        </tr>
                    @endforelse
        <x-slot:pagination>
            <x-table-pagination :paginator="$auditorias" label="Paginación de auditoría" />
        </x-slot:pagination>
    </x-data-table>
    {{-- Modal de detalle, también en blanco --}}
    @if ($mostrarDetalle && $auditoriaSeleccionada)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="tituloDetalleAuditoria">
            <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 id="tituloDetalleAuditoria" class="text-lg font-semibold text-gray-800">
                            Detalle de auditoría #{{ $auditoriaSeleccionada->id }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $auditoriaSeleccionada->usuario?->name ?? ($auditoriaSeleccionada->modelo === 'autenticacion' ? 'Sistema / No autenticado' : 'Usuario no disponible') }}
                            · {{ $auditoriaSeleccionada->created_at?->format('d/m/Y H:i:s') ?? 'Sin fecha' }}
                        </p>
                    </div>
                    <button type="button" wire:click="cerrarDetalle" aria-label="Cerrar detalle"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto p-6">
                    <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-gray-500">Módulo</p>
                            <p class="font-medium text-gray-800">
                                {{ $modulos[$auditoriaSeleccionada->modelo] ?? class_basename($auditoriaSeleccionada->modelo) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Registro</p>
                            <p class="font-medium text-gray-800">
                                @if ($auditoriaSeleccionada->modelo === 'autenticacion')
                                    {{ $auditoriaSeleccionada->usuarioAfectado?->name ?? 'Evento anónimo' }}
                                @else
                                    #{{ $auditoriaSeleccionada->registro_id }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Acción</p>
                            <p class="font-medium text-gray-800">
                                {{ $acciones[$auditoriaSeleccionada->accion] ?? ucfirst($auditoriaSeleccionada->accion) }}
                            </p>
                        </div>
                    </div>

                    @if ($auditoriaSeleccionada->ip_address)
                        <div class="text-sm">
                            <p class="text-gray-500">Dirección IP</p>
                            <p class="font-medium text-gray-800">{{ $auditoriaSeleccionada->ip_address }}</p>
                        </div>
                    @endif

                    @php
                        $anteriores = $auditoriaSeleccionada->valores_anteriores ?? [];
                        $nuevos = $auditoriaSeleccionada->valores_nuevos ?? [];
                        $campos = array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)));
                        $formatearValor = static function ($valor) {
                            if ($valor === null) return '—';
                            if (is_bool($valor)) return $valor ? 'Sí' : 'No';
                            if (is_array($valor)) return json_encode($valor, JSON_UNESCAPED_UNICODE);
                            return (string) $valor;
                        };
                    @endphp

                    <div>
                        <h3 class="mb-3 text-base font-semibold text-gray-800">Valores anteriores y nuevos</h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Campo</th>
                                        <th class="px-4 py-3">Valor anterior</th>
                                        <th class="px-4 py-3">Valor nuevo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse ($campos as $campo)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-gray-800">
                                                {{ str_replace('_', ' ', ucfirst($campo)) }}
                                            </td>
                                            <td class="max-w-xs break-words px-4 py-3 text-gray-600">
                                                {{ $formatearValor($anteriores[$campo] ?? null) }}
                                            </td>
                                            <td class="max-w-xs break-words px-4 py-3 text-gray-800">
                                                {{ $formatearValor($nuevos[$campo] ?? null) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                                Esta operación no tiene valores adicionales para mostrar.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end border-t border-gray-200 px-6 py-4">
                    <button type="button" wire:click="cerrarDetalle"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
