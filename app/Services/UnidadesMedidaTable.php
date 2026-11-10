<?php

namespace App\Services;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class UnidadesMedidaTable
{
    public const COLUMNS = [
        'nombre_singular' => 'Singular',
        'nombre_plural' => 'Plural',
        'abreviatura' => 'Abreviatura',
        'permite_decimales' => 'Decimales',
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
     * @return Builder<UnidadMedida>
     */
    public function query(array $filters): Builder
    {
        $query = UnidadMedida::query();
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('nombre_singular', 'like', $term)
                ->orWhere('nombre_plural', 'like', $term)
                ->orWhere('abreviatura', 'like', $term));
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('unidades_medida.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('unidades_medida.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(UnidadMedida $item): array
    {
        return [
            'nombre_singular' => $item->nombre_singular,
            'nombre_plural' => $item->nombre_plural,
            'abreviatura' => $item->abreviatura ?? '—',
            'permite_decimales' => $item->permite_decimales ? 'Sí' : 'No',
            'activo' => $item->activo ? 'Activa' : 'Inactiva',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(UnidadMedida $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<UnidadMedida> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre_singular')->orderByDesc('unidades_medida.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('unidades_medida.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('unidades_medida.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('unidades_medida.id');
    }
}
