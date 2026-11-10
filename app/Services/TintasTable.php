<?php

namespace App\Services;

use App\Models\Tinta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class TintasTable
{
    public const COLUMNS = [
        'nombre' => 'Tinta',
        'color' => 'Color',
        'presentacion' => 'Presentación',
        'existencia' => 'Existencia',
        'activo' => 'Estado',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return app(TableExport::class)->validate($input, self::COLUMNS, [
            'filtroColor' => ['nullable', 'string', 'max:50'],
            'filtroEstado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Tinta>
     */
    public function query(array $filters): Builder
    {
        $query = Tinta::query()->with('marca');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $q) => $q->where('nombre', 'like', $term)
                ->orWhere('color', 'like', $term)
                ->orWhere('presentacion', 'like', $term)
                ->orWhereHas('marca', fn (Builder $q) => $q->where('nombre', 'like', $term)));
        }
        if (($filters['filtroColor'] ?? '') !== '') {
            $query->where('color', $filters['filtroColor']);
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('tintas.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('tintas.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Tinta $item, array $columns): array
    {
        $bottles = $item->botellas_completas;
        $existence = $bottles.($bottles === 1 ? ' botella completa' : ' botellas completas');
        $open = (float) $item->porcentaje_botella_abierta;
        if ($open > 0) {
            $percentage = rtrim(rtrim(number_format($open, 2, '.', ''), '0'), '.');
            $existence .= ' + '.$percentage.' % de una botella abierta';
        }
        $existence .= ' / Equivalente: '.number_format($item->existencia_equivalente, 2).' botellas';
        if ($item->existencia_equivalente <= (float) $item->stock_minimo_botellas) {
            $existence .= ' / Stock bajo';
        }
        $values = [
            'nombre' => $item->nombre.($item->marca ? ' / '.$item->marca->nombre : ''),
            'color' => $item->color,
            'presentacion' => $item->presentacion ?? '—',
            'existencia' => $existence,
            'activo' => $item->activo ? 'Activa' : 'Inactiva',
        ];

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Tinta> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('tintas.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('tintas.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'existencia') {
            $query->orderByRaw('(tintas.botellas_completas + tintas.porcentaje_botella_abierta / 100.0) '.$sortDirection);
        } else {
            $query->orderBy('tintas.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('tintas.id');
    }
}
