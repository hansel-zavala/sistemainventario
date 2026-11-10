<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class CategoriasTable
{
    public const COLUMNS = [
        'nombre' => 'Categoría',
        'aplica_a' => 'Aplica a',
        'creado_por' => 'Creado por',
        'activo' => 'Estado',
    ];

    public const TYPES = [
        'equipo' => 'Equipo',
        'herramienta' => 'Herramienta',
        'insumo' => 'Insumo',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return app(TableExport::class)->validate($input, self::COLUMNS, [
            'filtroTipo' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'filtroEstado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Categoria>
     */
    public function query(array $filters): Builder
    {
        $query = Categoria::query()->with('creador:id,name');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $query->where('nombre', 'like', '%'.$search.'%');
        }
        if (($filters['filtroTipo'] ?? '') !== '') {
            $query->where('aplica_a', $filters['filtroTipo']);
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('categorias.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('categorias.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(Categoria $item): array
    {
        return [
            'nombre' => $item->nombre,
            'aplica_a' => self::TYPES[$item->aplica_a] ?? $item->aplica_a,
            'creado_por' => $item->creador?->name ?? 'No disponible',
            'activo' => $item->activo ? 'Activa' : 'Inactiva',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Categoria $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Categoria> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('categorias.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('categorias.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'creado_por') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'categorias.creado_por'), $sortDirection);
        } else {
            $query->orderBy('categorias.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('categorias.id');
    }
}
