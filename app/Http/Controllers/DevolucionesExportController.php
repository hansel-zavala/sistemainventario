<?php

namespace App\Http\Controllers;

use App\Services\DevolucionesTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DevolucionesExportController extends Controller
{
    public function __invoke(Request $request, DevolucionesTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, DevolucionesTable::COLUMNS, DevolucionesTable::filterRules($input));

        return $export->respondWithRows(DevolucionesTable::COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Devoluciones de activos', 'devoluciones',
            dateLabel: DevolucionesTable::DATE_TYPES[$filters['tipoFecha'] ?? DevolucionesTable::DEFAULT_DATE]);
    }
}
