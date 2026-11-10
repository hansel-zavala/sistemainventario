<?php

namespace App\Services;

use Illuminate\Contracts\View\View;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TableExport
{
    /** @param array<string, mixed> $input
     * @param array<string, string> $labels
     * @param array<string, array> $extraRules
     * @return array<string, mixed>
     */
    public function validate(array $input, array $labels, array $extraRules = []): array
    {
        return Validator::make($input, [
            'buscar' => ['nullable', 'string', 'max:200'],
            'sortColumn' => ['nullable', Rule::in(array_keys($labels))],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['required', 'array', 'min:1', 'max:'.count($labels)],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys($labels))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...(! empty($input['desde']) ? ['after_or_equal:desde'] : [])],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'print'])],
            ...$extraRules,
        ], [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'columns.required' => 'Seleccione al menos una columna.',
            'columns.min' => 'Seleccione al menos una columna.',
        ])->validate();
    }

    /** @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param array<string, string> $labels
     * @param array<string, mixed> $filters
     * @param callable(\Illuminate\Database\Eloquent\Model, array<int, string>): array<int, string> $row
     */
    public function response(Builder $query, array $labels, array $filters, callable $row, string $title, string $filename, string $emptyMessage = 'No se encontraron registros con estos filtros.'): View|StreamedResponse
    {
        $columns = array_values(array_intersect(array_keys($labels), $filters['columns']));
        $headers = array_map(fn (string $column): string => $labels[$column], $columns);
        $format = $filters['format'] ?? 'csv';

        if ($format === 'print') {
            return view('components.data-table-print', [
                'headers' => $headers,
                'title' => $title,
                'emptyMessage' => $emptyMessage,
                'rows' => $query->lazy(500)->map(fn ($item): array => $row($item, $columns)),
                'desde' => $filters['desde'] ?? null,
                'hasta' => $filters['hasta'] ?? null,
            ]);
        }

        return response()->streamDownload(function () use ($format, $query, $headers, $columns, $row): void {
            if ($format === 'xlsx') {
                $writer = new Writer;
                $writer->openToFile('php://output');
                try {
                    $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value, null), $headers)));
                    foreach ($query->lazy(500) as $item) {
                        $writer->addRow(new Row(array_map(fn (string $value): StringCell => new StringCell($value, null), $row($item, $columns))));
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
                foreach ($query->lazy(500) as $item) {
                    $values = array_map(
                        fn (string $value): string => preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value) ? "'".$value : $value,
                        $row($item, $columns),
                    );
                    fputcsv($output, $values, ',', '"', '');
                }
            } finally {
                fclose($output);
            }
        }, $filename.'-'.now()->format('Y-m-d-His').'.'.$format, [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
