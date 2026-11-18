<?php

namespace App\Http\Controllers;

use App\Services\RecargasTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecargasExportController extends Controller
{
    public function __invoke(Request $request, RecargasTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, RecargasTable::COLUMNS, RecargasTable::filterRules($input));

        return $export->respondWithRows(RecargasTable::COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Recargas de tinta', 'recargas-tinta', dateLabel: 'Fecha de recarga');
    }
}
