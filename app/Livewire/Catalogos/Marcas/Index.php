<?php

namespace App\Livewire\Catalogos\Marcas;

use App\Models\Marca;
use App\Services\MarcasTable;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const COLUMNS = MarcasTable::COLUMNS;

    public array $visibleColumns = ['nombre', 'creado_por', 'activo'];

    public string $sortColumn = '';

    public string $sortDirection = '';

    public function setVisibleColumns(array $columns): void
    {
        $columns = array_values(array_intersect(array_keys(self::COLUMNS), array_filter($columns, 'is_string')));
        if ($columns !== []) {
            $this->visibleColumns = $columns;
        }
    }

    public function toggleColumn(string $column): void
    {
        abort_unless(array_key_exists($column, self::COLUMNS), 422);
        $this->setVisibleColumns(in_array($column, $this->visibleColumns, true)
            ? array_diff($this->visibleColumns, [$column])
            : [...$this->visibleColumns, $column]);
    }

    public function sortBy(string $column): void
    {
        abort_unless(array_key_exists($column, self::COLUMNS), 422);
        if ($this->sortColumn !== $column) {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        } elseif ($this->sortDirection === 'asc') {
            $this->sortDirection = 'desc';
        } else {
            $this->sortColumn = '';
            $this->sortDirection = '';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('buscar', 'filtroEstado');
        $this->resetPage();
    }

    public function removeStatusFilter(): void
    {
        $this->reset('filtroEstado');
        $this->resetPage();
    }

    public string $buscar = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $marcaId = null;

    public string $nombre = '';

    public bool $activoMarca = true;

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function crearMarca(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }

    public function editarMarca(int $id): void
    {
        $marca = Marca::findOrFail($id);

        $this->marcaId = $marca->id;
        $this->nombre = $marca->nombre;
        $this->activoMarca = $marca->activo;

        $this->resetValidation();

        $this->mostrarModal = true;
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;

        $this->resetValidation();

        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->marcaId = null;
        $this->nombre = '';
        $this->activoMarca = true;
    }

    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'activoMarca' => [
                'boolean',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la marca es obligatorio.',

            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        ];
    }

    public function guardarMarca(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);
        $esCreacion = $this->marcaId === null;

        $guardado = DB::transaction(function () use (
            $nombreNormalizado,
            $esCreacion
        ) {

            /* Bloquear la marca cuando estamos editando */

            $marca = null;

            if (! $esCreacion) {
                $marca = Marca::query()
                    ->whereKey($this->marcaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar que no exista otra marca con el mismo nombre */

            $duplicada = Marca::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombreNormalizado)]
                )
                ->when(
                    ! $esCreacion,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $this->marcaId
                    )
                )
                ->exists();

            if ($duplicada) {
                return false;
            }

            $datos = [
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoMarca,
            ];

            /* Crear marca */

            if ($esCreacion) {

                $datos['creado_por'] = Auth::id();

                $marca = Marca::create($datos);

                RegistrarAuditoria::creacion($marca, [
                    'nombre',
                    'activo',
                ]);
            } else {

                /* Detectar y guardar solamente los cambios reales */

                $marca->fill($datos);

                RegistrarAuditoria::guardarActualizacion($marca);
            }

            return true;
        }, 3);

        /* Mostrar error si el nombre está duplicado */

        if (! $guardado) {
            $this->addError(
                'nombre',
                'Ya existe una marca con este nombre.'
            );

            return;
        }

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Marca creada correctamente.'
                : 'Marca actualizada correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }

    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            $marca = Marca::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $marca->activo;
            $nuevoEstado = ! $estadoAnterior;

            $marca->update([
                'activo' => $nuevoEstado,
            ]);

            RegistrarAuditoria::cambioEstado(
                $marca,
                $estadoAnterior,
                $nuevoEstado
            );

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Marca activada correctamente.'
                : 'Marca desactivada correctamente.'
        );
    }

    public function render()
    {
        $marcas = app(MarcasTable::class)->query([
            'buscar' => $this->buscar,
            'filtroEstado' => $this->filtroEstado,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
        ])->paginate(10);

        return view(
            'livewire.catalogos.marcas.index',
            compact('marcas')
        );
    }
}
