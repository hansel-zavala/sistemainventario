<?php

namespace App\Services;

use App\Models\RecargaTinta;
use App\Models\RecargaTintaDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class RecargasTable
{
    public const COLUMNS = [
        'recarga' => 'N.º de recarga', 'fecha' => 'Fecha', 'impresora' => 'Impresora',
        'inventario' => 'Inventario', 'serie' => 'Serie', 'responsable' => 'Técnico',
        'tinta' => 'Tinta', 'color' => 'Color', 'anterior' => 'Nivel anterior (%)',
        'agregado' => 'Porcentaje agregado', 'posterior' => 'Nivel posterior (%)', 'observaciones' => 'Observaciones',
    ];

    public static function filterRules(array $filters): array
    {
        $rules = MovimientosTable::filterRules($filters);
        unset($rules['tipoArticulo']);

        return [...$rules,
            'buscar' => ['nullable', 'string', 'max:200'],
            'filtroEquipo' => ['nullable', 'bail', 'integer', 'exists:equipos,id'],
            'filtroColor' => ['nullable', 'string', 'max:50'],
            'filtroUsuario' => ['nullable', 'bail', 'integer', 'exists:users,id'],
        ];
    }

    /** @return Builder<RecargaTinta> */
    public function query(array $filters): Builder
    {
        $query = RecargaTinta::query()->with(['equipo.tipoEquipo', 'equipo.marca', 'usuario',
            'detalles' => fn ($details) => $details->orderBy('id'), 'detalles.tinta.marca']);
        foreach (['filtroEquipo' => 'equipo_id', 'filtroUsuario' => 'realizado_por'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }
        $color = $filters['filtroColor'] ?? '';
        if ($color !== '') {
            $query->whereHas('detalles.tinta', fn (Builder $ink) => $ink->where('color', $color));
        }
        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $refills) => $refills->where('observaciones', 'like', $term)
                ->orWhereHas('equipo', fn (Builder $equipment) => $equipment
                    ->where('numero_inventario', 'like', $term)->orWhere('numero_serie', 'like', $term)
                    ->orWhere('modelo', 'like', $term)->orWhereHas('marca', fn (Builder $brand) => $brand->where('nombre', 'like', $term)))
                ->orWhereHas('usuario', fn (Builder $user) => $user->where('name', 'like', $term))
                ->orWhereHas('detalles.tinta', function (Builder $ink) use ($color, $term): void {
                    if ($color !== '') {
                        $ink->where('color', $color);
                    }
                    $ink->where(fn (Builder $match) => $match->where('nombre', 'like', $term)->orWhere('color', 'like', $term)
                        ->orWhereHas('marca', fn (Builder $brand) => $brand->where('nombre', 'like', $term)));
                }));
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }
        $direction = ($filters['orden'] ?? MovimientosTable::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);

        return $query;
    }

    public function exportRows(array $filters, array $columns): LazyCollection
    {
        return $this->query($filters)->lazy(500)->flatMap(fn (RecargaTinta $refill) => $refill->detalles->map(
            function (RecargaTintaDetalle $detail) use ($refill, $columns): array {
                $values = [
                    'recarga' => (string) $refill->id, 'fecha' => $refill->created_at->format('d/m/Y H:i'),
                    'impresora' => $refill->equipo === null ? 'Impresora no disponible' : trim(($refill->equipo->marca?->nombre ?? '').' '.$refill->equipo->modelo),
                    'inventario' => $refill->equipo?->numero_inventario ?? '', 'serie' => $refill->equipo?->numero_serie ?? '',
                    'responsable' => $refill->usuario?->name ?? 'Usuario no disponible',
                    'tinta' => $detail->tinta?->nombre ?? 'Tinta no disponible', 'color' => $detail->tinta?->color ?? '',
                    'anterior' => $detail->porcentaje_antes, 'agregado' => $detail->porcentaje_agregado,
                    'posterior' => $detail->porcentaje_despues, 'observaciones' => $refill->observaciones ?? '',
                ];

                return array_map(fn (string $column): string => $values[$column], $columns);
            }
        ));
    }
}
