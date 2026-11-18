<?php

namespace App\Http\Controllers;

use App\Services\AuditoriasTable;
use App\Services\TableExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriasExportController extends Controller
{
    public function __invoke(Request $request, AuditoriasTable $table, TableExport $export): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);

        $input = $request->query();
        $filters = $export->validate($input, AuditoriasTable::COLUMNS, AuditoriasTable::filterRules($input));

        return $export->response($table->query($filters), AuditoriasTable::COLUMNS, $filters,
            AuditoriasTable::row(...), 'Auditoría del sistema', 'auditoria');
    }
}
