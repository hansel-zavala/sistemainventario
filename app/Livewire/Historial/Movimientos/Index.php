<?php

namespace App\Livewire\Historial\Movimientos;

use App\Services\HistorialMovimientosTable;
use App\Services\MovimientosTable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    private const FILTERS = ['filtroTipo', 'filtroArticuloTipo', 'filtroResponsable', 'desde', 'hasta', 'orden'];

    public string $buscar = '';

    public string $filtroTipo = '';

    public string $filtroArticuloTipo = '';

    public string $filtroResponsable = '';

    public string $desde = '';

    public string $hasta = '';

    public string $orden = MovimientosTable::DEFAULT_ORDER;

    public function updating(string $property): void
    {
        if ($property === 'buscar' || in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function removeFilter(string $filter): void
    {
        abort_unless(in_array($filter, self::FILTERS, true), 422);

        $this->reset($filter);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

    public function render(HistorialMovimientosTable $table): View
    {
        $filters = [
            'filtroTipo' => $this->filtroTipo,
            'filtroArticuloTipo' => $this->filtroArticuloTipo,
            'filtroResponsable' => $this->filtroResponsable,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'orden' => $this->orden,
        ];
        $criteria = [...$filters, 'buscar' => $this->buscar];
        $filterErrors = Validator::make($criteria, HistorialMovimientosTable::filterRules($criteria), [
            ...MovimientosTable::FILTER_MESSAGES,
            'filtroTipo.in' => 'Seleccione un movimiento válido.',
            'filtroArticuloTipo.in' => 'Seleccione un tipo de artículo válido.',
            'filtroResponsable.integer' => 'Seleccione un responsable válido.',
            'filtroResponsable.exists' => 'Seleccione un responsable válido.',
            'buscar.max' => 'La búsqueda admite hasta 200 caracteres.',
        ])->errors();
        $query = $table->query($filterErrors->isEmpty() ? $criteria : []);
        if ($filterErrors->isNotEmpty()) {
            $query->whereRaw('1 = 0');
        }
        $eventosHistorial = $query->paginate(10);
        $usuarios = DB::table('users')->orderBy('name')->orderBy('id')->pluck('name', 'id')->all();

        return view('livewire.historial.movimientos.index', compact('eventosHistorial', 'usuarios', 'filters', 'filterErrors'));
    }
}
