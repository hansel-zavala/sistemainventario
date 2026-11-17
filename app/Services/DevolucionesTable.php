<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\PrestamoDevolucion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;

class DevolucionesTable extends PrestamosTable
{
    public const DEFAULT_STATE = 'pendientes';

    public const DEFAULT_DATE = 'prevista';

    public const STATES = [self::DEFAULT_STATE => 'Pendientes', 'vencidos' => 'Vencidos', 'completados' => 'Completados', 'todos' => 'Todos'];

    public const DATE_TYPES = [self::DEFAULT_DATE => 'Fecha prevista', 'registrada' => 'Fecha registrada'];

    public const COLUMNS = [
        'prestamo' => 'N.º de préstamo', 'destino' => 'Departamento destino', 'receptor' => 'Responsable',
        'tipo' => 'Tipo de activo', 'articulo' => 'Activo', 'codigo' => 'Código', 'estado' => 'Estado del préstamo',
        'estado_activo' => 'Estado del activo', 'prestado' => 'Cantidad prestada', 'devuelto' => 'Total devuelto',
        'pendiente' => 'Cantidad pendiente', 'fecha_prevista' => 'Fecha prevista', 'fecha_registrada' => 'Fecha registrada',
        'cantidad_entregada' => 'Cantidad de la entrega', 'recibido_por' => 'Recibido por', 'observaciones' => 'Observaciones de la entrega',
    ];

    public const FILTER_MESSAGES = [
        ...parent::FILTER_MESSAGES,
        'tipoFecha.in' => 'Seleccione una fecha de referencia válida.',
    ];

    public static function filterRules(array $filters): array
    {
        return [
            ...parent::filterRules($filters),
            'filtroEstado' => ['nullable', 'string', Rule::in(array_keys(self::STATES))],
            'tipoFecha' => ['nullable', 'string', Rule::in(array_keys(self::DATE_TYPES))],
        ];
    }

    /** @return Builder<Prestamo> */
    public function query(array $filters): Builder
    {
        $query = $this->loanQuery($filters);
        $state = $filters['filtroEstado'] ?? self::DEFAULT_STATE;
        $registered = ($filters['tipoFecha'] ?? self::DEFAULT_DATE) === 'registrada';
        $direction = ($filters['orden'] ?? MovimientosTable::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';

        if (in_array($state, [self::DEFAULT_STATE, 'vencidos'], true)) {
            $query->where('prestamos.estado', 'activo')
                ->whereHas('detalles', fn (Builder $details) => $details->where('estado', 'prestado'));
            if ($state === 'vencidos') {
                $query->where('fecha_prevista_devolucion', '<', now());
            }
        } elseif ($state === 'completados') {
            $query->where('prestamos.estado', 'completado');
        }

        $query->with(['detalles.devoluciones' => function (HasMany $returns) use ($registered, $filters): void {
            if ($registered) {
                /** @var Builder<PrestamoDevolucion> $returnsQuery */
                $returnsQuery = $returns->getQuery();
                $this->restrictReturns($returnsQuery, $filters);
            }
            $returns->with('usuario:id,name')->orderBy('fecha_devolucion')->orderBy('id');
        }]);

        if ($registered) {
            $latestReturns = PrestamoDevolucion::query()
                ->join('prestamo_detalles', 'prestamo_detalles.id', '=', 'prestamo_devoluciones.prestamo_detalle_id')
                ->select('prestamo_detalles.prestamo_id')
                ->selectRaw('MAX(prestamo_devoluciones.fecha_devolucion) as ultima_entrega')
                ->groupBy('prestamo_detalles.prestamo_id');
            $this->restrictReturns($latestReturns, $filters);
            $query->select('prestamos.*')
                ->joinSub($latestReturns, 'entregas', 'entregas.prestamo_id', '=', 'prestamos.id')
                ->orderBy('entregas.ultima_entrega', $direction);
        } else {
            $this->restrictDate($query, 'fecha_prevista_devolucion', $filters);
            $query->orderBy('fecha_prevista_devolucion', $direction);
        }

        $query->orderBy('prestamos.id', $direction);

        return $query;
    }

    /** @param Builder<PrestamoDevolucion> $returns */
    private function restrictReturns(Builder $returns, array $filters): void
    {
        $this->restrictDate($returns, 'prestamo_devoluciones.fecha_devolucion', $filters);

        $type = $filters['tipoArticulo'] ?? '';
        if ($type !== '') {
            $returns->whereHas('detalle', fn (Builder $details) => $this->restrictType($details, $type));
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function restrictDate(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['desde'])) {
            $query->whereDate($column, '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate($column, '<=', $filters['hasta']);
        }
    }

    public function exportRows(array $filters, array $columns): LazyCollection
    {
        $registered = ($filters['tipoFecha'] ?? self::DEFAULT_DATE) === 'registrada';

        return $this->query($filters)->lazy(500)->flatMap(fn (Prestamo $loan) => $loan->detalles->flatMap(
            function (PrestamoDetalle $detail) use ($loan, $columns, $registered): array {
                if ($detail->devoluciones->isEmpty()) {
                    return $registered ? [] : [$this->exportRow($loan, $detail, null, $columns)];
                }

                return $detail->devoluciones->map(fn (PrestamoDevolucion $return): array => $this->exportRow($loan, $detail, $return, $columns))->all();
            }
        ));
    }

    private function exportRow(Prestamo $loan, PrestamoDetalle $detail, ?PrestamoDevolucion $return, array $columns): array
    {
        $values = [
            'prestamo' => (string) $loan->id,
            'destino' => $loan->departamentoDestino?->nombre ?? 'No disponible',
            'receptor' => $loan->persona_recibe,
            ...self::asset($detail),
            'estado' => $loan->esta_vencido ? 'Vencido' : ucfirst($loan->estado),
            'estado_activo' => ucfirst($detail->estado),
            'prestado' => $detail->cantidad_prestada,
            'devuelto' => $detail->cantidad_devuelta,
            'pendiente' => number_format($detail->cantidad_pendiente, 2, '.', ''),
            'fecha_prevista' => $loan->fecha_prevista_devolucion->format('d/m/Y H:i'),
            'fecha_registrada' => $return?->fecha_devolucion->format('d/m/Y H:i') ?? '',
            'cantidad_entregada' => $return?->cantidad ?? '',
            'recibido_por' => $return === null ? '' : ($return->usuario?->name ?? 'No disponible'),
            'observaciones' => $return?->observaciones ?? '',
        ];

        return array_map(fn (string $column): string => $values[$column], $columns);
    }
}
