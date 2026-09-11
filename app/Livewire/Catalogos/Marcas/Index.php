<?php

namespace App\Livewire\Catalogos\Marcas;

use App\Models\Marca;
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
            'nombre.required' =>
                'El nombre de la marca es obligatorio.',

            'nombre.max' =>
                'El nombre no puede superar los 100 caracteres.',
        ];
    }


    public function guardarMarca(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);

        $duplicada = Marca::query()
            ->whereRaw(
                'LOWER(TRIM(nombre)) = ?',
                [mb_strtolower($nombreNormalizado)]
            )
            ->when(
                $this->marcaId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->marcaId
                    );
                }
            )
            ->exists();

        if ($duplicada) {
            $this->addError(
                'nombre',
                'Ya existe una marca con este nombre.'
            );

            return;
        }

        if ($this->marcaId === null) {

            Marca::create([
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoMarca,
                'creado_por' => Auth::id(),
            ]);

            session()->flash(
                'mensaje',
                'Marca creada correctamente.'
            );

        } else {

            $marca = Marca::findOrFail(
                $this->marcaId
            );

            $marca->update([
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoMarca,
            ]);

            session()->flash(
                'mensaje',
                'Marca actualizada correctamente.'
            );
        }

        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $marca = Marca::findOrFail($id);

        $marca->update([
            'activo' => ! $marca->activo,
        ]);

        session()->flash(
            'mensaje',
            $marca->activo
                ? 'Marca activada correctamente.'
                : 'Marca desactivada correctamente.'
        );
    }


    public function render()
    {
        $marcas = Marca::query()
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
            'livewire.catalogos.marcas.index',
            compact('marcas')
        );
    }
}