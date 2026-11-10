<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class DepartamentosTable
{
    public const COLUMNS = [
        'nombre' => 'Departamento',
        'sigla' => 'Sigla',
        'creado_por' => 'Creado por',
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
     * @return Builder<Departamento>
     */
    public function query(array $filters): Builder
    {
        $query = Departamento::query()->with('creador:id,name');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('nombre', 'like', $term)
                ->orWhere('sigla', 'like', $term));
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('departamentos.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('departamentos.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(Departamento $item): array
    {
        return [
            'nombre' => $item->nombre,
            'sigla' => $item->sigla ?? '—',
            'creado_por' => $item->creador?->name ?? 'No disponible',
            'activo' => $item->activo ? 'Activo' : 'Inactivo',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Departamento $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Departamento> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('departamentos.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('departamentos.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'creado_por') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'departamentos.creado_por'), $sortDirection);
        } else {
            $query->orderBy('departamentos.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('departamentos.id');
    }
}
