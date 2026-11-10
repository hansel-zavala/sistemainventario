@include('components.data-table-print', [
    'title' => 'Equipos tecnológicos',
    'rows' => $equipos->map(fn ($equipo) => \App\Services\EquiposTable::row($equipo, $columns)),
    'emptyMessage' => 'No se encontraron equipos con estos filtros.',
])
