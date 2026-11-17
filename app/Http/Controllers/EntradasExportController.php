<?php

namespace App\Http\Controllers;

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
            fn (array $columns): LazyCollection => $table->exportRows($filters, $columns),
            'Entradas de inventario', 'entradas');
    }
}
