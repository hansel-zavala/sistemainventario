<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class InsumosTable
{
    public const COLUMNS = [
        'nombre' => 'Insumo',
        'categoria' => 'Categoría',
        'marca' => 'Marca',
        'cantidad_actual' => 'Existencia',
        'stock_minimo' => 'Stock mínimo',
        'activo' => 'Estado',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return app(TableExport::class)->validate($input, self::COLUMNS, [
            'filtroEstado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Insumo>
     */
    public function query(array $filters): Builder
    {
        $query = Insumo::query()->with(['categoria', 'marca', 'unidadMedida']);
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $q) => $q->where('nombre', 'like', $term)
                ->orWhereHas('marca', fn (Builder $q) => $q->where('nombre', 'like', $term))
                ->orWhereHas('categoria', fn (Builder $q) => $q->where('nombre', 'like', $term)));
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('insumos.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('insumos.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Insumo $item, array $columns): array
    {
        $quantity = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').' '.($value == 1 ? $item->unidadMedida->nombre_singular : $item->unidadMedida->nombre_plural);
        $values = [
            'nombre' => $item->nombre,
            'categoria' => $item->categoria->nombre,
            'marca' => $item->marca?->nombre ?? '—',
            'cantidad_actual' => $quantity((float) $item->cantidad_actual).((float) $item->cantidad_actual <= (float) $item->stock_minimo ? ' / Stock bajo' : ''),
            'stock_minimo' => $quantity((float) $item->stock_minimo),
            'activo' => $item->activo ? 'Activo' : 'Inactivo',
        ];

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Insumo> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('insumos.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('insumos.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            $column = match ($sortColumn) {
                'categoria' => Categoria::select('nombre')->whereColumn('categorias.id', 'insumos.categoria_id'),
                'marca' => Marca::select('nombre')->whereColumn('marcas.id', 'insumos.marca_id'),
                default => 'insumos.'.$sortColumn,
            };
            $query->orderBy($column, $sortDirection);
        }
        $query->orderByDesc('insumos.id');
    }

}
