<?php

namespace App\Http\Controllers;

use App\Services\CategoriasTable;
use App\Services\HerramientasTable;
use App\Services\InsumosTable;
use App\Services\MarcasTable;
use App\Services\TableExport;
use App\Services\TintasTable;
use App\Services\TiposEquipoTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function __invoke(Request $request, TableExport $export, string $module): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);
        [$table, $title] = match ($module) {
            'herramientas' => [app(HerramientasTable::class), 'Herramientas'],
            'insumos' => [app(InsumosTable::class), 'Insumos'],
            'tintas' => [app(TintasTable::class), 'Tintas'],
            'categorias' => [app(CategoriasTable::class), 'Categorías'],
            'tipos-equipo' => [app(TiposEquipoTable::class), 'Tipos de equipo'],
            'marcas' => [app(MarcasTable::class), 'Marcas'],
            default => abort(404),
        };
        $filters = $table->validate($request->query());

        return $export->response(
            $table->query($filters), $table::COLUMNS, $filters, $table::row(...),
            $title, $module,
        );
    }
}
