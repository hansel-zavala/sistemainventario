<?php

namespace App\Http\Controllers;

use App\Services\HistorialMovimientosTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialMovimientosExportController extends Controller
{
    public function __invoke(Request $request, HistorialMovimientosTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, HistorialMovimientosTable::EXPORT_COLUMNS, HistorialMovimientosTable::filterRules($input));

        return $export->respondWithRows(HistorialMovimientosTable::EXPORT_COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Historial de movimientos', 'historial-movimientos', dateLabel: 'Fecha del movimiento');
    }
}
