<?php

namespace App\Livewire\Catalogos\TiposEquipo;

use App\Models\TipoEquipo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $tipoEquipoId = null;

    public string $nombre = '';

    public bool $activoTipoEquipo = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearTipoEquipo(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarTipoEquipo(int $id): void
    {
        $tipoEquipo = TipoEquipo::findOrFail($id);

        $this->tipoEquipoId = $tipoEquipo->id;
        $this->nombre = $tipoEquipo->nombre;
        $this->activoTipoEquipo = $tipoEquipo->activo;

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
        $this->tipoEquipoId = null;
        $this->nombre = '';
        $this->activoTipoEquipo = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'activoTipoEquipo' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
                'El nombre del tipo de equipo es obligatorio.',

            'nombre.max' =>
                'El nombre no puede superar los 100 caracteres.',
        ];
    }


    public function guardarTipoEquipo(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);

        $duplicado = TipoEquipo::query()
            ->whereRaw(
                'LOWER(TRIM(nombre)) = ?',
                [mb_strtolower($nombreNormalizado)]
            )
            ->when(
                $this->tipoEquipoId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->tipoEquipoId
                    );
                }
            )
            ->exists();

        if ($duplicado) {
            $this->addError(
                'nombre',
                'Ya existe un tipo de equipo con este nombre.'
            );

            return;
        }

        if ($this->tipoEquipoId === null) {

            TipoEquipo::create([
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoTipoEquipo,
                'creado_por' => Auth::id(),
            ]);

            session()->flash(
                'mensaje',
                'Tipo de equipo creado correctamente.'
            );

        } else {

            $tipoEquipo = TipoEquipo::findOrFail(
                $this->tipoEquipoId
            );

            $tipoEquipo->update([
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoTipoEquipo,
            ]);

            session()->flash(
                'mensaje',
                'Tipo de equipo actualizado correctamente.'
            );
        }

        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $tipoEquipo = TipoEquipo::findOrFail($id);

        $tipoEquipo->update([
            'activo' => ! $tipoEquipo->activo,
        ]);

        session()->flash(
            'mensaje',
            $tipoEquipo->activo
                ? 'Tipo de equipo activado correctamente.'
                : 'Tipo de equipo desactivado correctamente.'
        );
    }


    public function render()
    {
        $tiposEquipo = TipoEquipo::query()
            ->with('creador')

            ->when(
                $this->buscar,
                function ($query) {
                    $query->where(
                        'nombre',
                        'like',
                        '%' . trim($this->buscar) . '%'
                    );
                }
            )

            ->when(
                $this->filtroEstado !== '',
                function ($query) {
                    $query->where(
                        'activo',
                        $this->filtroEstado === 'activo'
                    );
                }
            )

            ->orderBy('nombre')
            ->paginate(10);

        return view(
            'livewire.catalogos.tipos-equipo.index',
            compact('tiposEquipo')
        );
    }
}