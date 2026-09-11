<?php

namespace App\Livewire\Catalogos\Departamentos;

use App\Models\Departamento;
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

    public ?int $departamentoId = null;

    public string $nombre = '';

    public string $sigla = '';

    public bool $activoDepartamento = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearDepartamento(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarDepartamento(int $id): void
    {
        $departamento = Departamento::findOrFail($id);

        $this->departamentoId = $departamento->id;
        $this->nombre = $departamento->nombre;
        $this->sigla = $departamento->sigla ?? '';
        $this->activoDepartamento = $departamento->activo;

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
        $this->departamentoId = null;
        $this->nombre = '';
        $this->sigla = '';
        $this->activoDepartamento = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'sigla' => [
                'nullable',
                'string',
                'max:20',
            ],

            'activoDepartamento' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
                'El nombre del departamento es obligatorio.',

            'nombre.max' =>
                'El nombre no puede superar los 150 caracteres.',

            'sigla.max' =>
                'La sigla no puede superar los 20 caracteres.',
        ];
    }


    public function guardarDepartamento(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);

        $siglaNormalizada = trim($this->sigla);

        $duplicado = Departamento::query()
            ->whereRaw(
                'LOWER(TRIM(nombre)) = ?',
                [mb_strtolower($nombreNormalizado)]
            )
            ->when(
                $this->departamentoId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->departamentoId
                    );
                }
            )
            ->exists();

        if ($duplicado) {
            $this->addError(
                'nombre',
                'Ya existe un departamento con este nombre.'
            );

            return;
        }

        if ($this->departamentoId === null) {

            Departamento::create([
                'nombre' => $nombreNormalizado,
                'sigla' => $siglaNormalizada !== ''
                    ? strtoupper($siglaNormalizada)
                    : null,
                'activo' => $this->activoDepartamento,
                'creado_por' => Auth::id(),
            ]);

            session()->flash(
                'mensaje',
                'Departamento creado correctamente.'
            );

        } else {

            $departamento = Departamento::findOrFail(
                $this->departamentoId
            );

            $departamento->update([
                'nombre' => $nombreNormalizado,
                'sigla' => $siglaNormalizada !== ''
                    ? strtoupper($siglaNormalizada)
                    : null,
                'activo' => $this->activoDepartamento,
            ]);

            session()->flash(
                'mensaje',
                'Departamento actualizado correctamente.'
            );
        }

        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $departamento = Departamento::findOrFail($id);

        $departamento->update([
            'activo' => ! $departamento->activo,
        ]);

        session()->flash(
            'mensaje',
            $departamento->activo
                ? 'Departamento activado correctamente.'
                : 'Departamento desactivado correctamente.'
        );
    }


    public function render()
    {
        $departamentos = Departamento::query()
            ->with('creador')

            ->when(
                $this->buscar,
                function ($query) {
                    $query->where(function ($subQuery) {
                        $subQuery
                            ->where(
                                'nombre',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            )
                            ->orWhere(
                                'sigla',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            );
                    });
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
            'livewire.catalogos.departamentos.index',
            compact('departamentos')
        );
    }
}