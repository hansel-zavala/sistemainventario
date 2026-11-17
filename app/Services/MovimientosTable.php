<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;

abstract class MovimientosTable
{
    public const TYPES = ['herramienta' => 'Herramienta', 'insumo' => 'Insumo', 'tinta' => 'Tinta'];

    public const DEFAULT_ORDER = 'recientes';

    public const ORDERS = [self::DEFAULT_ORDER => 'Más recientes', 'antiguos' => 'Más antiguos'];

    public const FILTER_MESSAGES = [
        'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        'desde.date_format' => 'Ingrese una fecha inicial válida.',
        'hasta.date_format' => 'Ingrese una fecha final válida.',
        'tipoArticulo.in' => 'Seleccione un tipo de artículo válido.',
        'orden.in' => 'Seleccione un orden válido.',
    ];

    public static function filterRules(array $filters): array
    {
        return [
            'tipoArticulo' => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
            'orden' => ['nullable', 'string', Rule::in(array_keys(self::ORDERS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...(! empty($filters['desde']) ? ['after_or_equal:desde'] : [])],
        ];
    }

    /** @return Builder<MovimientoInventario> */
    protected function movementQuery(string $movementType, array $filters): Builder
    {
        $tipo = $filters['tipoArticulo'] ?? '';
        $search = trim($filters['buscar'] ?? '');
        $query = MovimientoInventario::query()->where('tipo', $movementType)
            ->with(['usuario:id,name', 'detalles' => function (HasMany $details): void {
                $this->joinItems($details->getQuery())
                    ->select('movimiento_inventario_detalles.*')
                    ->selectRaw('COALESCE(herramientas.nombre, insumos.nombre, tintas.nombre) as articulo_nombre, tintas.color as articulo_color')
                    ->orderBy('movimiento_inventario_detalles.id');
            }]);

        if ($tipo !== '') {
            $query->whereHas('detalles', fn (Builder $details) => $details->where('tipo_item', $tipo));
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $entries) use ($term, $tipo): void {
                $entries->where('observaciones', 'like', $term)
                    ->orWhereHas('usuario', fn (Builder $user) => $user->where('name', 'like', $term))
                    ->orWhereHas('detalles', function (Builder $details) use ($term, $tipo): void {
                        $this->joinItems($details)->when($tipo !== '', fn (Builder $items) => $items->where('tipo_item', $tipo))
                            ->where(fn (Builder $items) => $items->where('herramientas.nombre', 'like', $term)
                                ->orWhere('herramientas.codigo_interno', 'like', $term)
                                ->orWhere('insumos.nombre', 'like', $term)
                                ->orWhere('tintas.nombre', 'like', $term)
                                ->orWhere('tintas.color', 'like', $term));
                    });
            });
        }

        if (! empty($filters['desde'])) {
            $query->whereDate('fecha_movimiento', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('fecha_movimiento', '<=', $filters['hasta']);
        }

        $direction = ($filters['orden'] ?? self::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';

        $query->orderBy('fecha_movimiento', $direction)->orderBy('id', $direction);

        return $query;
    }

    public static function articleName(MovimientoInventarioDetalle $detail): string
    {
        $name = $detail->articulo_nombre ?? 'Artículo no disponible';

        return $detail->articulo_color === null ? $name : $name.' — '.$detail->articulo_color;
    }

    abstract public function query(array $filters): Builder;

    public function exportRows(array $filters, array $columns): LazyCollection
    {
        return $this->query($filters)->lazy(500)->flatMap(fn (MovimientoInventario $movement) => $movement->detalles->map(
            function (MovimientoInventarioDetalle $detail) use ($movement, $columns): array {
                $values = $this->exportValues($movement, $detail);

                return array_map(fn (string $column): string => $values[$column], $columns);
            }
        ));
    }

    protected function exportValues(MovimientoInventario $movement, MovimientoInventarioDetalle $detail): array
    {
        return [
            'fecha' => $movement->fecha_movimiento->format('d/m/Y H:i'),
            'usuario' => $movement->usuario?->name ?? 'Usuario no disponible',
            'tipo' => self::TYPES[$detail->tipo_item] ?? $detail->tipo_item,
            'articulo' => self::articleName($detail),
            'anterior' => $detail->existencia_anterior,
            'cantidad' => $detail->cantidad,
            'posterior' => $detail->existencia_posterior,
            'observaciones' => $movement->observaciones ?? '',
        ];
    }

    private function joinItems(Builder $details): Builder
    {
        foreach (['herramienta' => 'herramientas', 'insumo' => 'insumos', 'tinta' => 'tintas'] as $type => $table) {
            $details->leftJoin($table, fn (JoinClause $join) => $join
                ->on($table.'.id', '=', 'movimiento_inventario_detalles.item_id')
                ->where('movimiento_inventario_detalles.tipo_item', $type));
        }

        return $details;
    }
}
