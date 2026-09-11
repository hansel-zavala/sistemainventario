<?php

namespace App\Livewire\Catalogos\UnidadesMedida;

use App\Models\UnidadMedida;
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

    public ?int $unidadMedidaId = null;

    public string $nombreSingular = '';

    public string $nombrePlural = '';

    public string $abreviatura = '';

    public bool $permiteDecimales = false;

    public bool $activoUnidad = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearUnidadMedida(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarUnidadMedida(int $id): void
    {
        $unidad = UnidadMedida::findOrFail($id);

        $this->unidadMedidaId = $unidad->id;
        $this->nombreSingular = $unidad->nombre_singular;
        $this->nombrePlural = $unidad->nombre_plural;
        $this->abreviatura = $unidad->abreviatura ?? '';
        $this->permiteDecimales = $unidad->permite_decimales;
        $this->activoUnidad = $unidad->activo;

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
        $this->unidadMedidaId = null;
        $this->nombreSingular = '';
        $this->nombrePlural = '';
        $this->abreviatura = '';
        $this->permiteDecimales = false;
        $this->activoUnidad = true;
    }


    protected function rules(): array
    {
        return [
            'nombreSingular' => [
                'required',
                'string',
                'max:50',
            ],

            'nombrePlural' => [
                'required',
                'string',
                'max:50',
            ],

            'abreviatura' => [
                'nullable',
                'string',
                'max:15',
            ],

            'permiteDecimales' => [
                'boolean',
            ],

            'activoUnidad' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombreSingular.required' =>
                'El nombre en singular es obligatorio.',

            'nombreSingular.max' =>
                'El nombre en singular no puede superar los 50 caracteres.',

            'nombrePlural.required' =>
                'El nombre en plural es obligatorio.',

            'nombrePlural.max' =>
                'El nombre en plural no puede superar los 50 caracteres.',

            'abreviatura.max' =>
                'La abreviatura no puede superar los 15 caracteres.',
        ];
    }


    public function guardarUnidadMedida(): void
    {
        $this->validate();

        $singular = trim($this->nombreSingular);
        $plural = trim($this->nombrePlural);
        $abreviatura = trim($this->abreviatura);

        $duplicadoSingular = UnidadMedida::query()
            ->whereRaw(
                'LOWER(TRIM(nombre_singular)) = ?',
                [mb_strtolower($singular)]
            )
            ->when(
                $this->unidadMedidaId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->unidadMedidaId
                    );
                }
            )
            ->exists();

        if ($duplicadoSingular) {
            $this->addError(
                'nombreSingular',
                'Ya existe una unidad de medida con este nombre en singular.'
            );

            return;
        }

        $duplicadoPlural = UnidadMedida::query()
            ->whereRaw(
                'LOWER(TRIM(nombre_plural)) = ?',
                [mb_strtolower($plural)]
            )
            ->when(
                $this->unidadMedidaId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->unidadMedidaId
                    );
                }
            )
            ->exists();

        if ($duplicadoPlural) {
            $this->addError(
                'nombrePlural',
                'Ya existe una unidad de medida con este nombre en plural.'
            );

            return;
        }

        if ($this->unidadMedidaId === null) {

            UnidadMedida::create([
                'nombre_singular' => $singular,
                'nombre_plural' => $plural,
                'abreviatura' => $abreviatura !== ''
                    ? $abreviatura
                    : null,
                'permite_decimales' => $this->permiteDecimales,
                'activo' => $this->activoUnidad,
                'creado_por' => Auth::id(),
            ]);

            session()->flash(
                'mensaje',
                'Unidad de medida creada correctamente.'
            );

        } else {

            $unidad = UnidadMedida::findOrFail(
                $this->unidadMedidaId
            );

            $unidad->update([
                'nombre_singular' => $singular,
                'nombre_plural' => $plural,
                'abreviatura' => $abreviatura !== ''
                    ? $abreviatura
                    : null,
                'permite_decimales' => $this->permiteDecimales,
                'activo' => $this->activoUnidad,
            ]);

            session()->flash(
                'mensaje',
                'Unidad de medida actualizada correctamente.'
            );
        }

        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $unidad = UnidadMedida::findOrFail($id);

        $unidad->update([
            'activo' => ! $unidad->activo,
        ]);

        session()->flash(
            'mensaje',
            $unidad->activo
                ? 'Unidad de medida activada correctamente.'
                : 'Unidad de medida desactivada correctamente.'
        );
    }


    public function render()
    {
        $unidades = UnidadMedida::query()
            ->with('creador')

            ->when(
                $this->buscar,
                function ($query) {
                    $query->where(function ($subQuery) {
                        $subQuery
                            ->where(
                                'nombre_singular',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            )
                            ->orWhere(
                                'nombre_plural',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            )
                            ->orWhere(
                                'abreviatura',
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

            ->orderBy('nombre_singular')
            ->paginate(10);

        return view(
            'livewire.catalogos.unidades-medida.index',
            compact('unidades')
        );
    }
}