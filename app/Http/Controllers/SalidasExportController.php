<?php

namespace App\Http\Controllers;

use App\Services\SalidasTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalidasExportController extends Controller
{
    public function __invoke(Request $request, SalidasTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, SalidasTable::COLUMNS, SalidasTable::filterRules($input));

        return $export->respondWithRows(SalidasTable::COLUMNS, $filters,
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Salidas de inventario', 'salidas');
    }
}
