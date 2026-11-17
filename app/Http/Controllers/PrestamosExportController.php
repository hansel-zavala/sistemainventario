<?php

namespace App\Http\Controllers;

use App\Services\PrestamosTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrestamosExportController extends Controller
{
    public function __invoke(Request $request, PrestamosTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, PrestamosTable::COLUMNS, PrestamosTable::filterRules($input));

        return $export->respondWithRows(PrestamosTable::COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Préstamos de activos', 'prestamos');
    }
}
