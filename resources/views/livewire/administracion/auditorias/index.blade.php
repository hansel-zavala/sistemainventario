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

    {{-- Filtros: mismo estilo que Historial de movimientos --}}
    <div class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-gray-800">Filtros de búsqueda</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="auditoriaBuscar" class="mb-1 block text-sm font-medium text-gray-700">Buscar</label>
                <input id="auditoriaBuscar" type="text" wire:model.live.debounce.400ms="buscar"
                       placeholder="Usuario, módulo, acción o IP"
                       class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="auditoriaModulo" class="mb-1 block text-sm font-medium text-gray-700">Módulo</label>
                <select id="auditoriaModulo" wire:model.live="filtroModulo"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los módulos</option>
                    @foreach ($modulos as $clase => $nombre)
                        <option value="{{ $clase }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="auditoriaAccion" class="mb-1 block text-sm font-medium text-gray-700">Acción</label>
                <select id="auditoriaAccion" wire:model.live="filtroAccion"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todas las acciones</option>
                    @foreach ($acciones as $clave => $nombre)
                        <option value="{{ $clave }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="auditoriaUsuario" class="mb-1 block text-sm font-medium text-gray-700">Responsable</label>
                <select id="auditoriaUsuario" wire:model.live="filtroUsuario"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los usuarios</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="auditoriaFechaDesde" class="mb-1 block text-sm font-medium text-gray-700">Fecha desde</label>
                <input id="auditoriaFechaDesde" type="date" wire:model.live="fechaDesde"
                       class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="auditoriaFechaHasta" class="mb-1 block text-sm font-medium text-gray-700">Fecha hasta</label>
                <input id="auditoriaFechaHasta" type="date" wire:model.live="fechaHasta"
                       class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" wire:click="limpiarFiltros"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- Registro centralizado --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-gray-800">Historial de auditoría</h2>
                <p class="mt-1 text-xs text-gray-500">Consulta de operaciones administrativas y eventos de autenticación.</p>
            </div>
            <span class="text-sm text-gray-500">{{ $auditorias->total() }} registros</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-5 py-3">Responsable</th>
                        <th class="px-5 py-3">Módulo</th>
                        <th class="px-5 py-3">Registro</th>
                        <th class="px-5 py-3">Acción</th>
                        <th class="px-5 py-3 text-right">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($auditorias as $auditoria)
                        <tr wire:key="auditoria-{{ $auditoria->id }}" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-4 text-gray-600">
                                {{ $auditoria->created_at?->format('d/m/Y H:i:s') ?? 'Sin fecha' }}
                            </td>
                            <td class="px-5 py-4 text-gray-600">{{ $auditoria->usuario?->name ?? ($auditoria->modelo === 'autenticacion' ? 'Sistema / No autenticado' : 'Usuario no disponible') }}</td>
                            <td class="px-5 py-4 font-medium text-gray-800">
                                {{ $modulos[$auditoria->modelo] ?? class_basename($auditoria->modelo) }}
                            </td>
                            <td class="px-5 py-4 text-gray-600">
                                @if ($auditoria->modelo === 'autenticacion')
                                    {{ $auditoria->usuarioAfectado?->name ?? 'Evento anónimo' }}
                                @else
                                    #{{ $auditoria->registro_id }}
                                @endif
                            </td>
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
                                    {{ $acciones[$auditoria->accion] ?? ucfirst($auditoria->accion) }}
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
                                No se encontraron registros de auditoría con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-200 px-5 py-4">{{ $auditorias->links() }}</div>
    </div>

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
