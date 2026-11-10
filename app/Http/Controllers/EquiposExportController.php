<?php

namespace App\Http\Controllers;

use App\Services\EquiposTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquiposExportController extends Controller
{
    public function __invoke(Request $request, EquiposTable $table): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);
        $filters = $table->validate($request->query());
        return app(TableExport::class)->response(
            $table->query($filters), EquiposTable::COLUMNS, $filters,
            EquiposTable::row(...), 'Equipos tecnológicos', 'equipos', 'No se encontraron equipos con estos filtros.',
        );
    }
}
