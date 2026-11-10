<?php

namespace App\Services;

use App\Models\Marca;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class MarcasTable
{
    public const COLUMNS = [
        'nombre' => 'Marca',
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
     * @return Builder<Marca>
     */
    public function query(array $filters): Builder
    {
        $query = Marca::query()->with('creador:id,name');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $query->where('nombre', 'like', '%'.$search.'%');
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('marcas.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('marcas.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(Marca $item): array
    {
        return [
            'nombre' => $item->nombre,
            'creado_por' => $item->creador?->name ?? 'No disponible',
            'activo' => $item->activo ? 'Activa' : 'Inactiva',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Marca $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Marca> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('marcas.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('marcas.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'creado_por') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'marcas.creado_por'), $sortDirection);
        } else {
            $query->orderBy('marcas.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('marcas.id');
    }
}
