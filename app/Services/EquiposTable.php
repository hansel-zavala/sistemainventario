<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Marca;
use App\Models\PrestamoDetalle;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EquiposTable
{
    public const COLUMNS = [
        'numero_inventario' => 'Inventario',
        'tipo_equipo' => 'Equipo',
        'marca' => 'Marca / Modelo',
        'numero_serie' => 'Serie',
        'departamento' => 'Procedencia',
        'disponibilidad' => 'Disponibilidad',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return Validator::make($input, [
            'buscar' => ['nullable', 'string', 'max:200'],
            'categoria' => ['nullable', 'integer', 'exists:categorias,id'],
            'tipo' => ['nullable', 'integer', 'exists:tipos_equipo,id'],
            'marca' => ['nullable', 'integer', 'exists:marcas,id'],
            'departamento' => ['nullable', 'integer', 'exists:departamentos,id'],
            'estado' => ['nullable', Rule::in(['disponible', 'prestado', 'inactivo'])],
            'sortColumn' => ['nullable', Rule::in(array_keys(self::COLUMNS))],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['required', 'array', 'min:1', 'max:6'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::COLUMNS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...(! empty($input['desde']) ? ['after_or_equal:desde'] : [])],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'print'])],
        ], [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'columns.min' => 'Seleccione al menos una columna.',
        ])->validate();
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Equipo>
     */
    public function query(array $filters): Builder
    {
        $query = Equipo::query()->with(['categoria', 'tipoEquipo', 'marca', 'departamento'])
            ->withExists(['prestamos as esta_prestado' => fn (Builder $q) => $q->where('estado', 'prestado')]);

        if ($search = trim($filters['buscar'] ?? '')) {
            $query->where(function (Builder $q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where('numero_inventario', 'like', $term)
                    ->orWhere('numero_serie', 'like', $term)->orWhere('modelo', 'like', $term)
                    ->orWhereHas('marca', fn (Builder $q) => $q->where('nombre', 'like', $term))
                    ->orWhereHas('tipoEquipo', fn (Builder $q) => $q->where('nombre', 'like', $term));
            });
        }
        foreach (['categoria' => 'categoria_id', 'tipo' => 'tipo_equipo_id', 'marca' => 'marca_id', 'departamento' => 'departamento_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        $state = $filters['estado'] ?? '';
        if ($state === 'inactivo') {
            $query->where('activo', false);
        } elseif (in_array($state, ['disponible', 'prestado'], true)) {
            $query->where('activo', true);
            $pending = fn (Builder $q) => $q->where('estado', 'prestado');
            if ($state === 'prestado') {
                $query->whereHas('prestamos', $pending);
            } else {
                $query->whereDoesntHave('prestamos', $pending);
            }
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('equipos.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('equipos.created_at', '<=', $filters['hasta']);
        }
        $column = $filters['sortColumn'] ?? '';
        $direction = $filters['sortDirection'] ?? '';
        if (isset(self::COLUMNS[$column]) && in_array($direction, ['asc', 'desc'], true)) {
            $sort = match ($column) {
                'tipo_equipo' => TipoEquipo::select('nombre')->whereColumn('tipos_equipo.id', 'equipos.tipo_equipo_id'),
                'marca' => Marca::select('nombre')->whereColumn('marcas.id', 'equipos.marca_id'),
                'departamento' => Departamento::select('nombre')->whereColumn('departamentos.id', 'equipos.departamento_id'),
                'disponibilidad' => 'equipos.activo',
                default => 'equipos.'.$column,
            };
            if ($column === 'disponibilidad') {
                $pending = PrestamoDetalle::selectRaw('1')->whereColumn('equipo_id', 'equipos.id')->where('estado', 'prestado');
                $query->orderByRaw('CASE WHEN equipos.activo = ? THEN 1 WHEN EXISTS ('.$pending->toSql().') THEN 2 ELSE 0 END '.$direction, [false, ...$pending->getBindings()]);
            } else {
                $query->orderBy($sort, $direction);
            }
            if ($column === 'marca') {
                $query->orderBy('modelo', $direction);
            }
        } else {
            $query->latest('equipos.created_at');
        }

        return $query->orderByDesc('equipos.id');
    }

    public static function status(Equipo $equipo): string
    {
        return ! $equipo->activo ? 'Inactivo' : ($equipo->esta_prestado ? 'Prestado' : 'Disponible');
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Equipo $equipo, array $columns): array
    {
        $values = [
            'numero_inventario' => $equipo->numero_inventario,
            'tipo_equipo' => $equipo->tipoEquipo->nombre.' / '.$equipo->categoria->nombre,
            'marca' => $equipo->marca->nombre.($equipo->modelo ? ' / '.$equipo->modelo : ''),
            'numero_serie' => $equipo->numero_serie ?? '—',
            'departamento' => $equipo->departamento->nombre.($equipo->departamento->sigla ? ' ('.$equipo->departamento->sigla.')' : ''),
            'disponibilidad' => self::status($equipo),
        ];

        return array_map(fn (string $column): string => (string) $values[$column], $columns);
    }
}
