<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;

class PrestamosTable
{
    public const TYPES = ['equipo' => 'Equipo', 'herramienta_individual' => 'Herramienta individual', 'herramienta_cantidad' => 'Herramienta por cantidad'];

    public const STATES = ['activo' => 'Activos', 'vencido' => 'Vencidos', 'completado' => 'Completados'];

    public const COLUMNS = [
        'prestamo' => 'N.º de préstamo', 'fecha' => 'Fecha del préstamo', 'devolucion' => 'Devolución prevista',
        'destino' => 'Departamento destino', 'receptor' => 'Persona que recibe', 'usuario' => 'Registrado por',
        'estado' => 'Estado del préstamo', 'tipo' => 'Tipo de activo', 'articulo' => 'Activo', 'codigo' => 'Código',
        'prestado' => 'Cantidad prestada', 'devuelto' => 'Cantidad devuelta', 'pendiente' => 'Cantidad pendiente',
        'estado_detalle' => 'Estado del activo', 'observaciones' => 'Observaciones',
    ];

    public const FILTER_MESSAGES = [
        ...MovimientosTable::FILTER_MESSAGES,
        'destino.integer' => 'Seleccione un departamento destino válido.',
        'destino.exists' => 'Seleccione un departamento destino válido.',
        'filtroEstado.in' => 'Seleccione un estado válido.',
    ];

    public static function filterRules(array $filters): array
    {
        return [
            ...MovimientosTable::filterRules($filters),
            'tipoArticulo' => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
            'destino' => ['nullable', 'bail', 'integer', 'exists:departamentos,id'],
            'filtroEstado' => ['nullable', 'string', Rule::in(array_keys(self::STATES))],
        ];
    }

    /** @return Builder<Prestamo> */
    public function query(array $filters): Builder
    {
        $query = $this->loanQuery($filters);
        $state = $filters['filtroEstado'] ?? '';

        if ($state !== '') {
            $query->where('estado', $state === 'vencido' ? 'activo' : $state);
        }
        if ($state === 'vencido') {
            $query->where('fecha_prevista_devolucion', '<', now());
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('fecha_prestamo', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('fecha_prestamo', '<=', $filters['hasta']);
        }

        $direction = ($filters['orden'] ?? MovimientosTable::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';
        $query->orderBy('fecha_prestamo', $direction)->orderBy('id', $direction);

        return $query;
    }

    /** @return Builder<Prestamo> */
    protected function loanQuery(array $filters): Builder
    {
        $type = $filters['tipoArticulo'] ?? '';
        $search = trim($filters['buscar'] ?? '');
        $query = Prestamo::query()->with(['departamentoDestino:id,nombre', 'usuario:id,name',
            'detalles' => fn ($details) => $details->orderBy('id'),
            'detalles.herramienta', 'detalles.equipo.marca', 'detalles.equipo.tipoEquipo']);

        if ($type !== '') {
            $query->whereHas('detalles', fn (Builder $details) => $this->restrictType($details, $type));
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $loans) use ($term, $type): void {
                $loans->where('persona_recibe', 'like', $term)
                    ->orWhere('observaciones', 'like', $term)
                    ->orWhereHas('usuario', fn (Builder $user) => $user->where('name', 'like', $term))
                    ->orWhereHas('departamentoDestino', fn (Builder $department) => $department->where('nombre', 'like', $term))
                    ->orWhereHas('detalles', function (Builder $details) use ($term, $type): void {
                        if ($type !== '') {
                            $this->restrictType($details, $type);
                        }

                        $details->where(fn (Builder $assets) => $assets
                            ->whereHas('herramienta', fn (Builder $tool) => $tool->where('nombre', 'like', $term)->orWhere('codigo_interno', 'like', $term))
                            ->orWhereHas('equipo', fn (Builder $equipment) => $equipment->where('numero_inventario', 'like', $term)
                                ->orWhere('modelo', 'like', $term)->orWhere('numero_serie', 'like', $term)
                                ->orWhereHas('tipoEquipo', fn (Builder $kind) => $kind->where('nombre', 'like', $term))
                                ->orWhereHas('marca', fn (Builder $brand) => $brand->where('nombre', 'like', $term))));
                    });
            });
        }

        if (! empty($filters['destino'])) {
            $query->where('departamento_destino_id', (int) $filters['destino']);
        }

        return $query;
    }

    protected function restrictType(Builder $details, string $type): void
    {
        if ($type === 'equipo') {
            $details->whereNotNull('equipo_id');

            return;
        }

        $control = $type === 'herramienta_cantidad' ? 'cantidad' : 'individual';
        $details->whereHas('herramienta', fn (Builder $tool) => $tool->where('tipo_control', $control));
    }

    /** @return array{tipo: string, articulo: string, codigo: string} */
    public static function asset(PrestamoDetalle $detail): array
    {
        if ($detail->equipo_id !== null) {
            $equipment = $detail->equipo;

            return ['tipo' => self::TYPES['equipo'],
                'articulo' => $equipment === null ? 'Equipo no disponible' : trim(implode(' ', array_filter([
                    $equipment->tipoEquipo?->nombre ?? 'Equipo', $equipment->marca?->nombre, $equipment->modelo,
                ]))), 'codigo' => $equipment?->numero_inventario ?? ''];
        }

        $tool = $detail->herramienta;

        return [
            'tipo' => self::TYPES['herramienta_'.$tool?->tipo_control] ?? 'Herramienta',
            'articulo' => $tool?->nombre ?? 'Herramienta no disponible',
            'codigo' => $tool?->codigo_interno ?? '',
        ];
    }

    public function exportRows(array $filters, array $columns): LazyCollection
    {
        return $this->query($filters)->lazy(500)->flatMap(fn (Prestamo $loan) => $loan->detalles->map(
            function (PrestamoDetalle $detail) use ($loan, $columns): array {
                $values = [
                    'prestamo' => (string) $loan->id,
                    'fecha' => $loan->fecha_prestamo->format('d/m/Y H:i'),
                    'devolucion' => $loan->fecha_prevista_devolucion->format('d/m/Y H:i'),
                    'destino' => $loan->departamentoDestino?->nombre ?? 'No disponible',
                    'receptor' => $loan->persona_recibe,
                    'usuario' => $loan->usuario?->name ?? 'No disponible',
                    'estado' => $loan->esta_vencido ? 'Vencido' : ucfirst($loan->estado),
                    ...self::asset($detail),
                    'prestado' => $detail->cantidad_prestada,
                    'devuelto' => $detail->cantidad_devuelta,
                    'pendiente' => number_format($detail->cantidad_pendiente, 2, '.', ''),
                    'estado_detalle' => ucfirst($detail->estado),
                    'observaciones' => $loan->observaciones ?? '',
                ];

                return array_map(fn (string $column): string => $values[$column], $columns);
            }
        ));
    }
}
