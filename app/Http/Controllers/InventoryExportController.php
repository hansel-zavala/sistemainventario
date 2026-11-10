<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HerramientasTable;
use App\Services\InsumosTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function __invoke(Request $request, TableExport $export, string $module): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);
        $table = match ($module) {
            'herramientas' => app(HerramientasTable::class),
            'insumos' => app(InsumosTable::class),
            default => abort(404),
        };
        $filters = $table->validate($request->query());

        return $export->response(
            $table->query($filters), $table::COLUMNS, $filters, $table::row(...),
            $module === 'herramientas' ? 'Herramientas' : 'Insumos', $module,
        );
    }
}
