<?php

namespace App\Livewire\Administracion\Auditorias;

use App\Models\Auditoria;
use App\Models\User;
use App\Services\AuditoriasTable;
use App\Services\MovimientosTable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    private const FILTERS = ['filtroModulo', 'filtroAccion', 'filtroUsuario', 'desde', 'hasta', 'orden'];

    public string $orden = MovimientosTable::DEFAULT_ORDER;

    public string $buscar = '';

    public string $filtroModulo = '';

    public string $filtroAccion = '';

    public string $filtroUsuario = '';

    public string $desde = '';

    public string $hasta = '';

    public bool $mostrarDetalle = false;

    public ?int $auditoriaSeleccionadaId = null;

    /* Seguridad */
    public function mount(): void
    {
        $this->autorizar();
    }

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

    /* Consultar detalle de una auditoría */

    public function verDetalle(int $id): void
    {
        $this->autorizar();

        $this->auditoriaSeleccionadaId = Auditoria::query()
            ->findOrFail($id)
            ->id;

        $this->mostrarDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->mostrarDetalle = false;
        $this->auditoriaSeleccionadaId = null;
    }

    private function autorizar(): void
    {
        $usuario = Auth::user();

        abort_unless(
            $usuario instanceof User
                && $usuario->roles()
                    ->where('name', 'Administrador')
                    ->exists(),
            403
        );
    }

    /* Consultar auditorías */

    public function render(AuditoriasTable $table): View
    {
        $this->autorizar();

        $filters = [
            'filtroModulo' => $this->filtroModulo,
            'filtroAccion' => $this->filtroAccion,
            'filtroUsuario' => $this->filtroUsuario,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'orden' => $this->orden,
        ];
        $criteria = [...$filters, 'buscar' => $this->buscar];
        $filterErrors = Validator::make($criteria, AuditoriasTable::filterRules($criteria), [
            ...MovimientosTable::FILTER_MESSAGES,
            'filtroModulo.in' => 'Seleccione un módulo válido.',
            'filtroAccion.in' => 'Seleccione una acción válida.',
            'filtroUsuario.integer' => 'Seleccione un responsable válido.',
            'filtroUsuario.exists' => 'Seleccione un responsable válido.',
            'buscar.max' => 'La búsqueda admite hasta 200 caracteres.',
        ])->errors();
        $query = $table->query($filterErrors->isEmpty() ? $criteria : []);
        if ($filterErrors->isNotEmpty()) {
            $query->whereRaw('1 = 0');
        }
        $auditorias = $query->paginate(10);
        $usuarios = User::query()->orderBy('name')->orderBy('id')->pluck('name', 'id')->all();

        $auditoriaSeleccionada = null;

        if (
            $this->mostrarDetalle
            && $this->auditoriaSeleccionadaId !== null
        ) {
            $auditoriaSeleccionada = Auditoria::query()
                ->with(['usuario:id,name', 'usuarioAfectado:id,name'])
                ->find($this->auditoriaSeleccionadaId);
        }

        return view(
            'livewire.administracion.auditorias.index',
            [
                'filterErrors' => $filterErrors,
                'filters' => $filters,
                'auditorias' => $auditorias,
                'usuarios' => $usuarios,
                'auditoriaSeleccionada' => $auditoriaSeleccionada,
                'modulos' => AuditoriasTable::MODULOS,
                'acciones' => AuditoriasTable::ACCIONES,
            ]
        );
    }
}
