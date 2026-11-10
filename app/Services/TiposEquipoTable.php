<?php

namespace App\Services;

use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class TiposEquipoTable
{
    public const COLUMNS = [
        'nombre' => 'Tipo de equipo',
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
     * @return Builder<TipoEquipo>
     */
    public function query(array $filters): Builder
    {
        $query = TipoEquipo::query()->with('creador:id,name');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $query->where('nombre', 'like', '%'.$search.'%');
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('tipos_equipo.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('tipos_equipo.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(TipoEquipo $item): array
    {
        return [
            'nombre' => $item->nombre,
            'creado_por' => $item->creador?->name ?? 'No disponible',
            'activo' => $item->activo ? 'Activo' : 'Inactivo',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(TipoEquipo $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<TipoEquipo> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('tipos_equipo.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('tipos_equipo.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'creado_por') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'tipos_equipo.creado_por'), $sortDirection);
        } else {
            $query->orderBy('tipos_equipo.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('tipos_equipo.id');
    }
}
