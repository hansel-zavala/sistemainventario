<?php

namespace App\Livewire\Inventario\Insumos;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\Marca;
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

    public bool $mostrarModalCatalogo = false;

    public string $tipoCatalogo = '';

    public string $nuevoNombreCatalogo = '';

    public string $nuevoNombrePluralUnidad = '';

    public string $nuevaAbreviaturaUnidad = '';

    public bool $nuevoPermiteDecimalesUnidad = false;

    public ?int $insumoId = null;

    public string $nombre = '';

    public string $categoriaId = '';

    public string $marcaId = '';

    public string $unidadMedidaId = '';

    public string $cantidadActual = '0';

    public string $stockMinimo = '0';

    public string $observaciones = '';

    public bool $activoInsumo = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearInsumo(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarInsumo(int $id): void
    {
        $insumo = Insumo::findOrFail($id);

        $this->insumoId = $insumo->id;
        $this->nombre = $insumo->nombre;
        $this->categoriaId = (string) $insumo->categoria_id;

        $this->marcaId = $insumo->marca_id
            ? (string) $insumo->marca_id
            : '';

        $this->unidadMedidaId =
            (string) $insumo->unidad_medida_id;

        $this->cantidadActual =
            (string) $insumo->cantidad_actual;

        $this->stockMinimo =
            (string) $insumo->stock_minimo;

        $this->observaciones =
            $insumo->observaciones ?? '';

        $this->activoInsumo =
            $insumo->activo;

        $this->resetValidation();

        $this->mostrarModal = true;
    }


    public function cerrarModal(): void
    {
        $this->mostrarModal = false;

        $this->resetValidation();

        $this->resetFormulario();
    }


    public function abrirModalCatalogo(string $tipo): void
    {
        $tiposPermitidos = [
            'categoria',
            'marca',
            'unidad_medida',
        ];

        if (! in_array($tipo, $tiposPermitidos, true)) {
            return;
        }

        $this->tipoCatalogo = $tipo;
        $this->nuevoNombreCatalogo = '';
        $this->nuevoNombrePluralUnidad = '';
        $this->nuevaAbreviaturaUnidad = '';
        $this->nuevoPermiteDecimalesUnidad = false;

        $this->resetValidation();

        $this->mostrarModalCatalogo = true;
    }


    public function cerrarModalCatalogo(): void
    {
        $this->mostrarModalCatalogo = false;
        $this->tipoCatalogo = '';
        $this->nuevoNombreCatalogo = '';
        $this->nuevoNombrePluralUnidad = '';
        $this->nuevaAbreviaturaUnidad = '';
        $this->nuevoPermiteDecimalesUnidad = false;

        $this->resetValidation();
    }


    protected function rulesCatalogoRapido(): array
    {
        $rules = [
            'nuevoNombreCatalogo' => [
                'required',
                'string',
                'max:150',
            ],
        ];

        if ($this->tipoCatalogo === 'unidad_medida') {
            $rules['nuevoNombreCatalogo'] = [
                'required',
                'string',
                'max:50',
            ];

            $rules['nuevoNombrePluralUnidad'] = [
                'required',
                'string',
                'max:50',
            ];

            $rules['nuevaAbreviaturaUnidad'] = [
                'nullable',
                'string',
                'max:15',
            ];

            $rules['nuevoPermiteDecimalesUnidad'] = [
                'boolean',
            ];
        }

        return $rules;
    }


    protected function messagesCatalogoRapido(): array
    {
        return [
            'nuevoNombreCatalogo.required' =>
                $this->tipoCatalogo === 'unidad_medida'
                    ? 'El nombre en singular es obligatorio.'
                    : 'El nombre es obligatorio.',

            'nuevoNombreCatalogo.max' =>
                $this->tipoCatalogo === 'unidad_medida'
                    ? 'El nombre en singular no puede superar los 50 caracteres.'
                    : 'El nombre no puede superar los 150 caracteres.',

            'nuevoNombrePluralUnidad.required' =>
                'El nombre en plural es obligatorio.',

            'nuevoNombrePluralUnidad.max' =>
                'El nombre en plural no puede superar los 50 caracteres.',

            'nuevaAbreviaturaUnidad.max' =>
                'La abreviatura no puede superar los 15 caracteres.',
        ];
    }


    public function guardarCatalogoRapido(): void
    {
        $this->validate(
            $this->rulesCatalogoRapido(),
            $this->messagesCatalogoRapido()
        );

        $nombre = trim($this->nuevoNombreCatalogo);

        if ($this->tipoCatalogo === 'categoria') {

            $duplicada = Categoria::query()
                ->where('aplica_a', 'insumo')
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicada) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe una categoría de insumos con este nombre.'
                );

                return;
            }

            $categoria = Categoria::create([
                'nombre' => $nombre,
                'aplica_a' => 'insumo',
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->categoriaId = (string) $categoria->id;
        }


        if ($this->tipoCatalogo === 'marca') {

            $duplicada = Marca::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicada) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe una marca con este nombre.'
                );

                return;
            }

            $marca = Marca::create([
                'nombre' => $nombre,
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->marcaId = (string) $marca->id;
        }


        if ($this->tipoCatalogo === 'unidad_medida') {

            $singular = $nombre;
            $plural = trim($this->nuevoNombrePluralUnidad);
            $abreviatura = trim($this->nuevaAbreviaturaUnidad);

            $duplicadoSingular = UnidadMedida::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre_singular)) = ?',
                    [mb_strtolower($singular)]
                )
                ->exists();

            if ($duplicadoSingular) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe una unidad de medida con este nombre en singular.'
                );

                return;
            }

            $duplicadoPlural = UnidadMedida::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre_plural)) = ?',
                    [mb_strtolower($plural)]
                )
                ->exists();

            if ($duplicadoPlural) {
                $this->addError(
                    'nuevoNombrePluralUnidad',
                    'Ya existe una unidad de medida con este nombre en plural.'
                );

                return;
            }

            $unidad = UnidadMedida::create([
                'nombre_singular' => $singular,
                'nombre_plural' => $plural,
                'abreviatura' => $abreviatura !== '' ? $abreviatura : null,
                'permite_decimales' => $this->nuevoPermiteDecimalesUnidad,
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->unidadMedidaId = (string) $unidad->id;
        }


        $this->cerrarModalCatalogo();

        session()->flash(
            'mensajeCatalogo',
            'Registro creado y seleccionado correctamente.'
        );
    }


    private function resetFormulario(): void
    {
        $this->insumoId = null;
        $this->nombre = '';
        $this->categoriaId = '';
        $this->marcaId = '';
        $this->unidadMedidaId = '';
        $this->cantidadActual = '0';
        $this->stockMinimo = '0';
        $this->observaciones = '';
        $this->activoInsumo = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'categoriaId' => [
                'required',
                'exists:categorias,id',
            ],

            'marcaId' => [
                'nullable',
                'exists:marcas,id',
            ],

            'unidadMedidaId' => [
                'required',
                'exists:unidades_medida,id',
            ],

            'cantidadActual' => [
                'required',
                'numeric',
                'min:0',
            ],

            'stockMinimo' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'activoInsumo' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
                'El nombre del insumo es obligatorio.',

            'nombre.max' =>
                'El nombre no puede superar los 150 caracteres.',

            'categoriaId.required' =>
                'Debe seleccionar una categoría.',

            'categoriaId.exists' =>
                'La categoría seleccionada no es válida.',

            'marcaId.exists' =>
                'La marca seleccionada no es válida.',

            'unidadMedidaId.required' =>
                'Debe seleccionar una unidad de medida.',

            'unidadMedidaId.exists' =>
                'La unidad de medida seleccionada no es válida.',

            'cantidadActual.required' =>
                'La cantidad actual es obligatoria.',

            'cantidadActual.numeric' =>
                'La cantidad actual debe ser un número.',

            'cantidadActual.min' =>
                'La cantidad actual no puede ser negativa.',

            'stockMinimo.required' =>
                'El stock mínimo es obligatorio.',

            'stockMinimo.numeric' =>
                'El stock mínimo debe ser un número.',

            'stockMinimo.min' =>
                'El stock mínimo no puede ser negativo.',

            'observaciones.max' =>
                'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }


    public function guardarInsumo(): void
    {
        $this->validate();

        $unidad = UnidadMedida::findOrFail(
            $this->unidadMedidaId
        );

        if (
            ! $unidad->permite_decimales
            && (
                fmod((float) $this->cantidadActual, 1.0) !== 0.0
                || fmod((float) $this->stockMinimo, 1.0) !== 0.0
            )
        ) {
            $this->addError(
                'cantidadActual',
                'La unidad de medida seleccionada no permite cantidades decimales.'
            );

            return;
        }


        $datos = [
            'nombre' =>
                trim($this->nombre),

            'categoria_id' =>
                (int) $this->categoriaId,

            'marca_id' =>
                $this->marcaId !== ''
                    ? (int) $this->marcaId
                    : null,

            'unidad_medida_id' =>
                (int) $this->unidadMedidaId,

            'cantidad_actual' =>
                (float) $this->cantidadActual,

            'stock_minimo' =>
                (float) $this->stockMinimo,

            'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

            'activo' =>
                $this->activoInsumo,
        ];


        if ($this->insumoId === null) {

            $datos['creado_por'] = Auth::id();

            Insumo::create($datos);

            session()->flash(
                'mensaje',
                'Insumo registrado correctamente.'
            );

        } else {

            $insumo = Insumo::findOrFail(
                $this->insumoId
            );

            $insumo->update($datos);

            session()->flash(
                'mensaje',
                'Insumo actualizado correctamente.'
            );
        }


        $this->cerrarModal();

        $this->resetPage();
    }


    public function cambiarEstado(int $id): void
    {
        $insumo = Insumo::findOrFail($id);

        $insumo->update([
            'activo' => ! $insumo->activo,
        ]);

        session()->flash(
            'mensaje',
            $insumo->activo
                ? 'Insumo activado correctamente.'
                : 'Insumo desactivado correctamente.'
        );
    }


    public function render()
    {
        $insumos = Insumo::query()
            ->with([
                'categoria',
                'marca',
                'unidadMedida',
            ])

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

                                ->orWhereHas(
                                    'marca',
                                    function ($marcaQuery) use ($buscar) {
                                        $marcaQuery->where(
                                            'nombre',
                                            'like',
                                            $buscar
                                        );
                                    }
                                )

                                ->orWhereHas(
                                    'categoria',
                                    function ($categoriaQuery) use ($buscar) {
                                        $categoriaQuery->where(
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


        $categorias = Categoria::query()
            ->where('activo', true)
            ->where('aplica_a', 'insumo')
            ->orderBy('nombre')
            ->get();


        $marcas = Marca::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        $unidades = UnidadMedida::query()
            ->where('activo', true)
            ->orderBy('nombre_singular')
            ->get();


        return view(
            'livewire.inventario.insumos.index',
            compact(
                'insumos',
                'categorias',
                'marcas',
                'unidades'
            )
        );
    }
}