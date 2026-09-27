<?php

namespace App\Livewire\Inventario\Tintas;

use App\Models\Marca;
use App\Models\Tinta;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroColor = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $tintaId = null;

    public string $nombre = '';

    public string $marcaId = '';

    public string $color = '';

    public string $presentacion = '';

    public string $botellasCompletas = '0';

    public string $porcentajeBotellaAbierta = '0';

    public string $stockMinimoBotellas = '1';

    public string $observaciones = '';

    public bool $activoTinta = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroColor(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearTinta(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarTinta(int $id): void
    {
        $tinta = Tinta::findOrFail($id);

        $this->tintaId = $tinta->id;
        $this->nombre = $tinta->nombre;

        $this->marcaId = $tinta->marca_id
            ? (string) $tinta->marca_id
            : '';

        $this->color = $tinta->color;

        $this->presentacion =
            $tinta->presentacion ?? '';

        $this->botellasCompletas =
            (string) $tinta->botellas_completas;

        $this->porcentajeBotellaAbierta =
            (string) $tinta->porcentaje_botella_abierta;

        $this->stockMinimoBotellas =
            (string) $tinta->stock_minimo_botellas;

        $this->observaciones =
            $tinta->observaciones ?? '';

        $this->activoTinta =
            $tinta->activo;

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
        $this->tintaId = null;
        $this->nombre = '';
        $this->marcaId = '';
        $this->color = '';
        $this->presentacion = '';
        $this->botellasCompletas = '0';
        $this->porcentajeBotellaAbierta = '0';
        $this->stockMinimoBotellas = '1';
        $this->observaciones = '';
        $this->activoTinta = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'marcaId' => [
                'nullable',
                'exists:marcas,id',
            ],

            'color' => [
                'required',
                'string',
                'max:50',
            ],

            'presentacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'botellasCompletas' => [
                'required',
                'integer',
                'min:0',
            ],

            'porcentajeBotellaAbierta' => [
                'required',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'stockMinimoBotellas' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'activoTinta' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
                'El nombre o referencia de la tinta es obligatorio.',

            'marcaId.exists' =>
                'La marca seleccionada no es válida.',

            'color.required' =>
                'El color de la tinta es obligatorio.',

            'botellasCompletas.required' =>
                'Debe indicar la cantidad de botellas completas.',

            'botellasCompletas.integer' =>
                'Las botellas completas deben registrarse con un número entero.',

            'botellasCompletas.min' =>
                'La cantidad de botellas no puede ser negativa.',

            'porcentajeBotellaAbierta.required' =>
                'Debe indicar el porcentaje restante de la botella abierta.',

            'porcentajeBotellaAbierta.numeric' =>
                'El porcentaje de la botella abierta debe ser un número.',

            'porcentajeBotellaAbierta.min' =>
                'El porcentaje no puede ser negativo.',

            'porcentajeBotellaAbierta.max' =>
                'La botella abierta debe tener un valor menor de 100 %.',

            'stockMinimoBotellas.required' =>
                'Debe indicar el stock mínimo.',

            'stockMinimoBotellas.numeric' =>
                'El stock mínimo debe ser un número.',

            'stockMinimoBotellas.min' =>
                'El stock mínimo no puede ser negativo.',

            'observaciones.max' =>
                'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }


    public function guardarTinta(): void
    {
        $this->validate();

        $nombre = trim($this->nombre);
        $color = trim($this->color);


        $duplicada = Tinta::query()
            ->whereRaw(
                'LOWER(TRIM(nombre)) = ?',
                [mb_strtolower($nombre)]
            )
            ->whereRaw(
                'LOWER(TRIM(color)) = ?',
                [mb_strtolower($color)]
            )
            ->when(
                $this->marcaId !== '',
                function ($query) {
                    $query->where(
                        'marca_id',
                        (int) $this->marcaId
                    );
                },
                function ($query) {
                    $query->whereNull('marca_id');
                }
            )
            ->when(
                $this->tintaId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->tintaId
                    );
                }
            )
            ->exists();


        if ($duplicada) {
            $this->addError(
                'nombre',
                'Ya existe una tinta con esta referencia, marca y color.'
            );

            return;
        }


        $datos = [
            'nombre' =>
                $nombre,

            'marca_id' =>
                $this->marcaId !== ''
                    ? (int) $this->marcaId
                    : null,

            'color' =>
                $color,

            'presentacion' =>
                trim($this->presentacion) !== ''
                    ? trim($this->presentacion)
                    : null,

            'botellas_completas' =>
                (int) $this->botellasCompletas,

            'porcentaje_botella_abierta' =>
                (float) $this->porcentajeBotellaAbierta,

            'stock_minimo_botellas' =>
                (float) $this->stockMinimoBotellas,

            'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

            'activo' =>
                $this->activoTinta,
        ];


        if ($this->tintaId === null) {

            $datos['creado_por'] = Auth::id();

            Tinta::create($datos);

            session()->flash(
                'mensaje',
                'Tinta registrada correctamente.'
            );

        } else {

            $tinta = Tinta::findOrFail(
                $this->tintaId
            );

            $tinta->update($datos);

            session()->flash(
                'mensaje',
                'Tinta actualizada correctamente.'
            );
        }


        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $tinta = Tinta::findOrFail($id);

        $tinta->update([
            'activo' => ! $tinta->activo,
        ]);

        session()->flash(
            'mensaje',
            $tinta->activo
                ? 'Tinta activada correctamente.'
                : 'Tinta desactivada correctamente.'
        );
    }


    public function render()
    {
        $tintas = Tinta::query()
            ->with('marca')

            ->when(
                $this->buscar,
                function ($query) {

                    $buscar =
                        '%' . trim($this->buscar) . '%';

                    $query->where(
                        function ($subQuery) use ($buscar) {

                            $subQuery
                                ->where(
                                    'nombre',
                                    'like',
                                    $buscar
                                )
                                ->orWhere(
                                    'color',
                                    'like',
                                    $buscar
                                )
                                ->orWhere(
                                    'presentacion',
                                    'like',
                                    $buscar
                                )
                                ->orWhereHas(
                                    'marca',
                                    function ($marcaQuery) use ($buscar) {
                                        $marcaQuery->where(
                                            'nombre',
                                            'like',
                                            $buscar
                                        );
                                    }
                                );
                        }
                    );
                }
            )

            ->when(
                $this->filtroColor,
                function ($query) {
                    $query->where(
                        'color',
                        $this->filtroColor
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


        $marcas = Marca::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        $colores = Tinta::query()
            ->select('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');


        return view(
            'livewire.inventario.tintas.index',
            compact(
                'tintas',
                'marcas',
                'colores'
            )
        );
    }
}