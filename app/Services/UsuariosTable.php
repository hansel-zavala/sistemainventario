<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UsuariosTable
{
    public const COLUMNS = [
        'name' => 'Usuario',
        'email' => 'Correo',
        'rol' => 'Rol',
        'activo' => 'Estado',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return app(TableExport::class)->validate($input, self::COLUMNS, [
            'rol' => ['nullable', Rule::in(['Administrador', 'Técnico'])],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<User>
     */
    public function query(array $filters): Builder
    {
        $query = User::query()->select(['id', 'name', 'email', 'activo'])
            ->with(['roles' => fn ($roles) => $roles->orderBy('roles.id')]);
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }
        if (! empty($filters['rol'])) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $filters['rol'])->where('guard_name', 'web'));
        }
        if (! empty($filters['estado'])) {
            $query->where('activo', $filters['estado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('users.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('users.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    /** @return array<string, string> */
    public static function values(User $item): array
    {
        return [
            'name' => $item->name,
            'email' => $item->email,
            'rol' => $item->getRoleNames()->first() ?? 'Sin rol',
            'activo' => $item->activo ? 'Activo' : 'Inactivo',
        ];
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(User $item, array $columns): array
    {
        $values = self::values($item);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<User> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('name')->orderByDesc('users.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'activo') {
            $query->orderBy('users.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } elseif ($sortColumn === 'rol') {
            $roleName = Role::query()->select('roles.name')
                ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->orderBy('roles.id')
                ->limit(1);
            $query->orderByRaw('COALESCE(('.$roleName->toSql().'), ?) '.$sortDirection, [...$roleName->getBindings(), 'Sin rol']);
        } else {
            $query->orderBy('users.'.$sortColumn, $sortDirection);
        }
        $query->orderByDesc('users.id');
    }
}
