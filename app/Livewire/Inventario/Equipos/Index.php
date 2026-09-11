<?php

namespace App\Livewire\Inventario\Equipos;

use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Marca;
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

    public bool $mostrarModal = false;

    public bool $mostrarModalCatalogo = false;

    public string $tipoCatalogo = '';

    public string $nuevoNombreCatalogo = '';

    public string $nuevaSiglaDepartamento = '';

    public ?int $equipoId = null;

    public string $categoriaId = '';

    public string $tipoEquipoId = '';

    public string $departamentoId = '';

    public string $marcaId = '';

    public string $modelo = '';

    public string $color = '';

    public string $numeroSerie = '';

    public string $numeroInventario = '';

    public string $observaciones = '';


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function crearEquipo(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarEquipo(int $id): void
    {
        $equipo = Equipo::findOrFail($id);

        $this->equipoId = $equipo->id;

        $this->categoriaId =
            (string) $equipo->categoria_id;

        $this->tipoEquipoId =
            (string) $equipo->tipo_equipo_id;

        $this->departamentoId =
            (string) $equipo->departamento_id;

        $this->marcaId =
            (string) $equipo->marca_id;

        $this->modelo =
            $equipo->modelo ?? '';

        $this->color =
            $equipo->color ?? '';

        $this->numeroSerie =
            $equipo->numero_serie ?? '';

        $this->numeroInventario =
            $equipo->numero_inventario;

        $this->observaciones =
            $equipo->observaciones ?? '';

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
            'tipo_equipo',
            'departamento',
            'marca',
        ];

        if (! in_array($tipo, $tiposPermitidos, true)) {
            return;
        }

        $this->tipoCatalogo = $tipo;
        $this->nuevoNombreCatalogo = '';
        $this->nuevaSiglaDepartamento = '';

        $this->resetValidation();

        $this->mostrarModalCatalogo = true;
    }

    public function cerrarModalCatalogo(): void
    {
        $this->mostrarModalCatalogo = false;
        $this->tipoCatalogo = '';
        $this->nuevoNombreCatalogo = '';
        $this->nuevaSiglaDepartamento = '';
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

        if ($this->tipoCatalogo === 'departamento') {
            $rules['nuevaSiglaDepartamento'] = [
                'nullable',
                'string',
                'max:20',
            ];
        }

        return $rules;
    }

    protected function messagesCatalogoRapido(): array
    {
        return [
            'nuevoNombreCatalogo.required' =>
                'El nombre es obligatorio.',

            'nuevoNombreCatalogo.max' =>
                'El nombre no puede superar los 150 caracteres.',

            'nuevaSiglaDepartamento.max' =>
                'La sigla no puede superar los 20 caracteres.',
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
                ->where('aplica_a', 'equipo')
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicada) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe una categoría con este nombre.'
                );

                return;
            }

            $categoria = Categoria::create([
                'nombre' => $nombre,
                'aplica_a' => 'equipo',
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->categoriaId = (string) $categoria->id;
        }


        if ($this->tipoCatalogo === 'tipo_equipo') {

            $duplicado = TipoEquipo::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicado) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe un tipo de equipo con este nombre.'
                );

                return;
            }

            $tipoEquipo = TipoEquipo::create([
                'nombre' => $nombre,
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->tipoEquipoId = (string) $tipoEquipo->id;
        }


        if ($this->tipoCatalogo === 'departamento') {

            $duplicado = Departamento::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicado) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe un departamento con este nombre.'
                );

                return;
            }

            $sigla = trim($this->nuevaSiglaDepartamento);

            $departamento = Departamento::create([
                'nombre' => $nombre,
                'sigla' => $sigla !== ''
                    ? strtoupper($sigla)
                    : null,
                'activo' => true,
                'creado_por' => Auth::id(),
            ]);

            $this->departamentoId =
                (string) $departamento->id;
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


        $this->cerrarModalCatalogo();

        session()->flash(
            'mensajeCatalogo',
            'Registro creado y seleccionado correctamente.'
        );
    }

    private function resetFormulario(): void
    {
        $this->equipoId = null;
        $this->categoriaId = '';
        $this->tipoEquipoId = '';
        $this->departamentoId = '';
        $this->marcaId = '';
        $this->modelo = '';
        $this->color = '';
        $this->numeroSerie = '';
        $this->numeroInventario = '';
        $this->observaciones = '';
    }


    protected function rules(): array
    {
        return [
            'categoriaId' => [
                'required',
                'exists:categorias,id',
            ],

            'tipoEquipoId' => [
                'required',
                'exists:tipos_equipo,id',
            ],

            'departamentoId' => [
                'required',
                'exists:departamentos,id',
            ],

            'marcaId' => [
                'required',
                'exists:marcas,id',
            ],

            'modelo' => [
                'nullable',
                'string',
                'max:100',
            ],

            'color' => [
                'nullable',
                'string',
                'max:50',
            ],

            'numeroSerie' => [
                'nullable',
                'string',
                'max:100',
            ],

            'numeroInventario' => [
                'required',
                'string',
                'max:100',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'categoriaId.required' =>
                'Debe seleccionar una categoría.',

            'categoriaId.exists' =>
                'La categoría seleccionada no es válida.',

            'tipoEquipoId.required' =>
                'Debe seleccionar un tipo de equipo.',

            'tipoEquipoId.exists' =>
                'El tipo de equipo seleccionado no es válido.',

            'departamentoId.required' =>
                'Debe seleccionar el departamento de procedencia.',

            'departamentoId.exists' =>
                'El departamento seleccionado no es válido.',

            'marcaId.required' =>
                'Debe seleccionar una marca.',

            'marcaId.exists' =>
                'La marca seleccionada no es válida.',

            'modelo.max' =>
                'El modelo no puede superar los 100 caracteres.',

            'color.max' =>
                'El color no puede superar los 50 caracteres.',

            'numeroSerie.max' =>
                'El número de serie no puede superar los 100 caracteres.',

            'numeroInventario.required' =>
                'El número de inventario es obligatorio.',

            'numeroInventario.max' =>
                'El número de inventario no puede superar los 100 caracteres.',

            'observaciones.max' =>
                'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }


    public function guardarEquipo(): void
    {
        $this->validate();

        $numeroInventario =
            trim($this->numeroInventario);

        $numeroSerie =
            trim($this->numeroSerie);


        $inventarioDuplicado = Equipo::query()
            ->whereRaw(
                'LOWER(TRIM(numero_inventario)) = ?',
                [mb_strtolower($numeroInventario)]
            )
            ->when(
                $this->equipoId !== null,
                function ($query) {
                    $query->where(
                        'id',
                        '!=',
                        $this->equipoId
                    );
                }
            )
            ->exists();

        if ($inventarioDuplicado) {
            $this->addError(
                'numeroInventario',
                'Ya existe un equipo con este número de inventario.'
            );

            return;
        }


        if ($numeroSerie !== '') {

            $serieDuplicada = Equipo::query()
                ->whereRaw(
                    'LOWER(TRIM(numero_serie)) = ?',
                    [mb_strtolower($numeroSerie)]
                )
                ->when(
                    $this->equipoId !== null,
                    function ($query) {
                        $query->where(
                            'id',
                            '!=',
                            $this->equipoId
                        );
                    }
                )
                ->exists();

            if ($serieDuplicada) {
                $this->addError(
                    'numeroSerie',
                    'Ya existe un equipo con este número de serie.'
                );

                return;
            }
        }


        $datos = [
            'categoria_id' =>
                (int) $this->categoriaId,

            'tipo_equipo_id' =>
                (int) $this->tipoEquipoId,

            'departamento_id' =>
                (int) $this->departamentoId,

            'marca_id' =>
                (int) $this->marcaId,

            'modelo' =>
                trim($this->modelo) !== ''
                    ? trim($this->modelo)
                    : null,

            'color' =>
                trim($this->color) !== ''
                    ? trim($this->color)
                    : null,

            'numero_serie' =>
                $numeroSerie !== ''
                    ? $numeroSerie
                    : null,

            'numero_inventario' =>
                $numeroInventario,

            'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,
        ];


        if ($this->equipoId === null) {

            $datos['creado_por'] = Auth::id();

            Equipo::create($datos);

            session()->flash(
                'mensaje',
                'Equipo registrado correctamente.'
            );

        } else {

            $equipo = Equipo::findOrFail(
                $this->equipoId
            );

            $equipo->update($datos);

            session()->flash(
                'mensaje',
                'Equipo actualizado correctamente.'
            );
        }


        $this->cerrarModal();

        $this->resetPage();
    }


    public function render()
    {
        $equipos = Equipo::query()
            ->with([
                'categoria',
                'tipoEquipo',
                'departamento',
                'marca',
                'creador',
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
                                    'numero_inventario',
                                    'like',
                                    $buscar
                                )
                                ->orWhere(
                                    'numero_serie',
                                    'like',
                                    $buscar
                                )
                                ->orWhere(
                                    'modelo',
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
                                    'tipoEquipo',
                                    function ($tipoQuery) use ($buscar) {
                                        $tipoQuery->where(
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

            ->latest()
            ->paginate(10);


        $categorias = Categoria::query()
            ->where('activo', true)
            ->where('aplica_a', 'equipo')
            ->orderBy('nombre')
            ->get();


        $tiposEquipo = TipoEquipo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        $departamentos = Departamento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        $marcas = Marca::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        return view(
            'livewire.inventario.equipos.index',
            compact(
                'equipos',
                'categorias',
                'tiposEquipo',
                'departamentos',
                'marcas'
            )
        );
    }
}