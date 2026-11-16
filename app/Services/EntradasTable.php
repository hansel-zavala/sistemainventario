<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Validation\Rule;

class EntradasTable
{
    public const TYPES = ['herramienta' => 'Herramienta', 'insumo' => 'Insumo', 'tinta' => 'Tinta'];

    public const COLUMNS = [
        'entrada' => 'N.º de entrada', 'fecha' => 'Fecha del movimiento', 'usuario' => 'Registrado por',
        'tipo' => 'Tipo de artículo', 'articulo' => 'Artículo', 'anterior' => 'Existencia anterior',
        'cantidad' => 'Cantidad ingresada', 'posterior' => 'Existencia posterior', 'observaciones' => 'Observaciones',
    ];

    public const FILTER_MESSAGES = [
        'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        'desde.date_format' => 'Ingrese una fecha inicial válida.',
        'hasta.date_format' => 'Ingrese una fecha final válida.',
        'tipoArticulo.in' => 'Seleccione un tipo de artículo válido.',
    ];

    public static function filterRules(array $filters): array
    {
        return [
            'tipoArticulo' => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...(! empty($filters['desde']) ? ['after_or_equal:desde'] : [])],
        ];
    }

    /** @return Builder<MovimientoInventario> */
    public function query(array $filters): Builder
    {
        $tipo = $filters['tipoArticulo'] ?? '';
        $search = trim($filters['buscar'] ?? '');
        $query = MovimientoInventario::query()->where('tipo', 'entrada')
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

        return $query->latest('fecha_movimiento')->orderByDesc('id');
    }

    public static function articleName(MovimientoInventarioDetalle $detail): string
    {
        $name = $detail->articulo_nombre ?? 'Artículo no disponible';

        return $detail->articulo_color === null ? $name : $name.' — '.$detail->articulo_color;
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
