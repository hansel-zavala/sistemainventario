<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipos tecnológicos — Exportación</title>
    <style>
        body { font: 12px system-ui, sans-serif; color: #243746; margin: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7dde5; text-align: left; padding: 8px; overflow-wrap: anywhere; }
        th { background: #f4f6f9; }
        button { padding: 10px 16px; cursor: pointer; }
        @page { size: landscape; margin: 12mm; }
        @media print { .print-controls { display: none; } body { margin: 0; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="print-controls"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>
    <h1>Equipos tecnológicos</h1>
    <p>Fecha de registro: {{ $desde ?: 'Sin inicio' }} — {{ $hasta ?: 'Sin límite' }}</p>
    <table>
        <thead><tr>@foreach ($headers as $header)<th scope="col">{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($equipos as $equipo)
                <tr>@foreach (\App\Services\EquiposTable::row($equipo, $columns) as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No se encontraron equipos con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
