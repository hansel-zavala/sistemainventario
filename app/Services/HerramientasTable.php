<?php

namespace App\Services;

use App\Models\Herramienta;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Database\Eloquent\Builder;
use App\Models\PrestamoDetalle;
use Illuminate\Validation\Rule;

class HerramientasTable
{
    public const COLUMNS = [
        'nombre' => 'Herramienta',
        'categoria' => 'Categoría',
        'tipo_control' => 'Control',
        'codigo_interno' => 'Código',
        'cantidad_actual' => 'Existencia',
        'disponibilidad' => 'Disponibilidad',
        'activo' => 'Estado',
    ];

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return app(TableExport::class)->validate($input, self::COLUMNS, [
            'filtroEstado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'filtroTipo' => ['nullable', Rule::in(['individual', 'cantidad'])],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Herramienta>
     */
    public function query(array $filters): Builder
    {
        $pending = PrestamoDetalle::query()
            ->selectRaw('COALESCE(SUM(cantidad_prestada - cantidad_devuelta), 0)')
            ->whereColumn('herramienta_id', 'herramientas.id')->where('estado', 'prestado');
        $query = Herramienta::query()->select('herramientas.*')
            ->with(['categoria', 'marca', 'unidadMedida'])
            ->withExists(['prestamos as esta_prestada' => fn (Builder $q) => $q->where('estado', 'prestado')])
            ->selectSub($pending, 'cantidad_prestada_pendiente');
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $q) => $q->where('nombre', 'like', $term)
                ->orWhere('codigo_interno', 'like', $term)
                ->orWhereHas('marca', fn (Builder $q) => $q->where('nombre', 'like', $term)));
        }
        if (! empty($filters['filtroTipo'])) {
            $query->where('tipo_control', $filters['filtroTipo']);
        }
        if (! empty($filters['filtroEstado'])) {
            $query->where('activo', $filters['filtroEstado'] === 'activo');
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('herramientas.created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('herramientas.created_at', '<=', $filters['hasta']);
        }
        $this->order($query, $filters['sortColumn'] ?? '', $filters['sortDirection'] ?? '');

        return $query;
    }

    public static function available(Herramienta $item): float
    {
        return max(0, round((float) $item->cantidad_actual - round((float) $item->cantidad_prestada_pendiente, 2), 2));
    }

    /** @param array<int, string> $columns
     * @return array<int, string>
     */
    public static function row(Herramienta $item, array $columns): array
    {
        $quantity = (float) $item->cantidad_actual;
        $existence = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.').' '.($quantity == 1 ? $item->unidadMedida->nombre_singular : $item->unidadMedida->nombre_plural);
        if ($item->tipo_control === 'cantidad' && $quantity <= (float) $item->stock_minimo) {
            $existence .= ' / Stock bajo';
        }
        if (! $item->activo) {
            $availability = 'Inactiva';
        } elseif ($item->tipo_control === 'individual') {
            $availability = $item->esta_prestada ? 'Prestada' : 'Disponible';
        } else {
            $available = self::available($item);
            $pending = round((float) $item->cantidad_prestada_pendiente, 2);
            $availability = $available > 0 ? number_format($available, 2, '.', '').' disponibles' : 'Sin disponibilidad';
            $availability .= $pending > 0 ? ' / '.number_format($pending, 2, '.', '').' prestadas' : ' / Sin préstamos pendientes';
        }
        $values = [
            'nombre' => $item->nombre.($item->marca ? ' / '.$item->marca->nombre : ''),
            'categoria' => $item->categoria->nombre,
            'tipo_control' => $item->tipo_control === 'individual' ? 'Individual' : 'Por cantidad',
            'codigo_interno' => $item->codigo_interno ?? '—',
            'cantidad_actual' => $existence,
            'disponibilidad' => $availability,
            'activo' => $item->activo ? 'Activa' : 'Inactiva',
        ];

        return array_map(fn (string $column): string => $values[$column], $columns);
    }

    /** @param Builder<Herramienta> $query */
    protected function order(Builder $query, string $sortColumn, string $sortDirection): void
    {
        if ($sortColumn === '' && $sortDirection === '') {
            $query->orderBy('nombre')->orderByDesc('herramientas.id');

            return;
        }
        abort_unless(isset(self::COLUMNS[$sortColumn]) && in_array($sortDirection, ['asc', 'desc'], true), 422);
        if ($sortColumn === 'disponibilidad') {
            $pending = PrestamoDetalle::query()
                ->selectRaw('COALESCE(SUM(cantidad_prestada - cantidad_devuelta), 0)')
                ->whereColumn('herramienta_id', 'herramientas.id')->where('estado', 'prestado');
            $sql = $pending->toSql();
            $query->orderByRaw(
                'CASE WHEN herramientas.activo = ? THEN 0 WHEN herramientas.cantidad_actual > ('.$sql.') THEN ROUND(herramientas.cantidad_actual - ('.$sql.'), 2) ELSE 0 END '.$sortDirection,
                [false, ...$pending->getBindings(), ...$pending->getBindings()],
            );
        } elseif ($sortColumn === 'tipo_control') {
            $query->orderByRaw("CASE WHEN herramientas.tipo_control = 'individual' THEN 0 ELSE 1 END ".$sortDirection);
        } elseif ($sortColumn === 'activo') {
            $query->orderBy('herramientas.activo', $sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            $column = match ($sortColumn) {
                'categoria' => Categoria::select('nombre')->whereColumn('categorias.id', 'herramientas.categoria_id'),
                default => 'herramientas.'.$sortColumn,
            };
            $query->orderBy($column, $sortDirection);
        }
        $query->orderByDesc('herramientas.id');
    }

}
