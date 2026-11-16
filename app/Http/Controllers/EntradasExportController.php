<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use App\Services\EntradasTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntradasExportController extends Controller
{
    public function __invoke(Request $request, EntradasTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, EntradasTable::COLUMNS, EntradasTable::filterRules($input));

        return $export->respondWithRows(EntradasTable::COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->query($filters)->lazy(500)
                ->flatMap(fn (MovimientoInventario $entry) => $entry->detalles->map(
                    fn (MovimientoInventarioDetalle $detail): array => $this->row($entry, $detail, $columns)
                )),
            'Entradas de inventario', 'entradas');
    }

    /** @param list<string> $columns
     * @return list<string>
     */
    private function row(MovimientoInventario $entry, MovimientoInventarioDetalle $detail, array $columns): array
    {
        $values = [
            'entrada' => (string) $entry->id,
            'fecha' => $entry->fecha_movimiento->format('d/m/Y H:i'),
            'usuario' => $entry->usuario?->name ?? 'Usuario no disponible',
            'tipo' => EntradasTable::TYPES[$detail->tipo_item] ?? $detail->tipo_item,
            'articulo' => EntradasTable::articleName($detail),
            'anterior' => $detail->existencia_anterior,
            'cantidad' => $detail->cantidad,
            'posterior' => $detail->existencia_posterior,
            'observaciones' => $entry->observaciones ?? '',
        ];

        return array_map(fn (string $column): string => $values[$column], $columns);
    }
}
