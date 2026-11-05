<?php

namespace App\Http\Controllers;

use App\Services\EquiposTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquiposExportController extends Controller
{
    public function __invoke(Request $request, EquiposTable $table): View|StreamedResponse
    {
        abort_unless($request->user()?->activo, 403);
        $filters = $table->validate($request->query());
        $columns = array_values(array_intersect(array_keys(EquiposTable::COLUMNS), $filters['columns']));
        $headers = array_map(fn (string $column): string => EquiposTable::COLUMNS[$column], $columns);
        $query = $table->query($filters);
        $format = $filters['format'] ?? 'csv';

        if ($format === 'print') {
            return view('livewire.inventario.equipos.print', [
                'headers' => $headers,
                'columns' => $columns,
                'equipos' => $query->lazy(500),
                'desde' => $filters['desde'] ?? null,
                'hasta' => $filters['hasta'] ?? null,
            ]);
        }

        return response()->streamDownload(function () use ($format, $query, $headers, $columns): void {
            if ($format === 'xlsx') {
                $writer = new Writer;
                $writer->openToFile('php://output');
                try {
                    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value, null), $headers)));
                    foreach ($query->lazy(500) as $equipo) {
                        $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value, null), EquiposTable::row($equipo, $columns))));
                    }
                } finally {
                    $writer->close();
                }

                return;
            }

            $output = fopen('php://output', 'wb');
            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers, ',', '"', '');
                foreach ($query->lazy(500) as $equipo) {
                    $values = array_map(
                        fn (string $value): string => preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value) ? "'".$value : $value,
                        EquiposTable::row($equipo, $columns),
                    );
                    fputcsv($output, $values, ',', '"', '');
                }
            } finally {
                fclose($output);
            }
        }, 'equipos-'.now()->format('Y-m-d-His').'.'.$format, [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
