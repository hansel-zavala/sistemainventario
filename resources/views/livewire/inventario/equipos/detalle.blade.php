@php
    $estado = \App\Services\EquiposTable::status($equipo);
    $departamento = $equipo->departamento;
    $identificacion = [
        'Número de inventario' => $equipo->numero_inventario,
        'Número de serie' => $equipo->numero_serie,
        'Categoría' => $equipo->categoria->nombre,
        'Tipo de equipo' => $equipo->tipoEquipo->nombre,
        'Departamento de procedencia' => $departamento->nombre.($departamento->sigla ? ' ('.$departamento->sigla.')' : ''),
        'Marca' => $equipo->marca->nombre,
    ];
    $caracteristicas = ['Modelo' => $equipo->modelo, 'Color' => $equipo->color];
    $registro = [
        'Registrado por' => $equipo->creador?->name,
        'Fecha de registro' => $equipo->created_at?->format('d/m/Y H:i'),
        'Última actualización' => $equipo->updated_at?->format('d/m/Y H:i'),
    ];
    $acciones = [
        'actualizacion' => 'Actualización de datos',
        'desactivacion' => 'Desactivación',
        'activacion' => 'Reactivación',
        'prestamo' => 'Préstamo',
        'devolucion' => 'Devolución',
    ];
    $descripcion = 'Inventario #'.$equipo->numero_inventario.(filled($equipo->modelo) ? ' · '.$equipo->modelo : '');
@endphp

<x-inventory-detail-dialog id="equipo-detalle" wire:key="detalle-equipo-{{ $equipo->id }}"
    trigger="ver-equipo-{{ $equipo->id }}" label="Ficha del equipo" icon="devices"
    :title="$equipo->tipoEquipo->nombre.' '.$equipo->marca->nombre" :description="$descripcion" :badge="$estado"
    :badge-tone="match ($estado) { 'Prestado' => 'warning', 'Disponible' => 'positive', default => 'neutral' }"
    edit-action="editarEquipo({{ $equipo->id }})" edit-label="Editar equipo">
    <x-slot:general>
        @foreach (['Identificación del equipo' => $identificacion, 'Características adicionales' => $caracteristicas, 'Registro' => $registro] as $seccion => $campos)
            <section class="py-4 sm:py-5">
                <h3 class="mb-3 text-sm font-semibold">{{ $seccion }}</h3>
                <dl class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                    @foreach ($campos as $etiqueta => $valor)
                        <div class="min-w-0">
                            <dt class="text-xs text-muted">{{ $etiqueta }}</dt>
                            <dd class="mt-0.5 text-sm font-medium wrap-anywhere">{{ filled($valor) ? $valor : 'Sin registrar' }}</dd>
                        </div>
                    @endforeach
                    @if ($seccion === 'Características adicionales')
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-muted">Observaciones</dt>
                            <dd @class(['mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap', 'text-muted italic' => blank($equipo->observaciones)])>{{ filled($equipo->observaciones) ? $equipo->observaciones : 'Sin observaciones registradas.' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endforeach
    </x-slot:general>

    <x-slot:tecnico>
        @if ($ingresosTaller === null)
            <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">La consulta de ingresos de Taller está disponible para técnicos y administradores.</p>
        @else
            @include('livewire.taller.ingresos.historial', ['historial' => $ingresosTaller, 'mensajeVacio' => 'Este equipo aún no tiene ingresos de Taller.'])
        @endif
    </x-slot:tecnico>

    <x-slot:movimientos>
        @if ($movimientos->isEmpty())
            <p class="rounded-xl border border-line bg-canvas/60 p-4 text-sm text-muted">Este equipo aún no tiene movimientos registrados.</p>
        @else
            <ol class="divide-y divide-line rounded-xl border border-line">
                @foreach ($movimientos as $movimiento)
                    @php($fecha = \Illuminate\Support\Carbon::parse($movimiento->fecha))
                    <li wire:key="equipo-movimiento-{{ $movimiento->fuente }}-{{ $movimiento->origen_id }}" class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">{{ $acciones[$movimiento->tipo] }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ $movimiento->responsable ?? 'Usuario no disponible' }}</p>
                        </div>
                        <time datetime="{{ $fecha->toIso8601String() }}" class="text-xs text-muted">{{ $fecha->format('d/m/Y H:i') }}</time>
                    </li>
                @endforeach
            </ol>
        @endif
        @if ($movimientos->hasPages())
            <div class="mt-4">
                <x-table-pagination :paginator="$movimientos" label="Paginación de movimientos del equipo" />
            </div>
        @endif
    </x-slot:movimientos>
</x-inventory-detail-dialog>
