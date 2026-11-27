<?php

namespace App\Livewire\Inventario\Equipos;

use App\Livewire\Inventario\CatalogosPorCategoria;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\IngresoTaller;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Services\EquiposTable;
use App\Services\HistorialMovimientosTable;
use App\Services\MovimientosTable;
use App\Services\RegistrarAuditoria;
use App\Services\RegistrarCatalogoEquipo;
use App\Services\RegistrarEquipo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use CatalogosPorCategoria;
    use WithPagination;

    private const TIPO_CATEGORIA = 'equipo';

    private const CAMPOS_EQUIPO = [
        'categoria_id' => 'categoriaId',
        'tipo_equipo_id' => 'tipoEquipoId',
        'departamento_id' => 'departamentoId',
        'marca_id' => 'marcaId',
        'modelo' => 'modelo',
        'color' => 'color',
        'numero_serie' => 'numeroSerie',
        'numero_inventario' => 'numeroInventario',
        'observaciones' => 'observaciones',
    ];

    public string $desde = '';

    public string $hasta = '';

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    public function removeDateFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['desde', 'hasta'], true), 422);

        $this->reset($filter);
        $this->resetPage();
    }

    public string $buscar = '';

    public bool $mostrarModal = false;

    public bool $mostrarModalCatalogo = false;

    public string $tipoCatalogo = '';

    public string $nuevoNombreCatalogo = '';

    public string $nuevaSiglaDepartamento = '';

    public ?int $equipoId = null;

    #[Locked]
    public ?int $equipoDetalleId = null;

    public string $categoriaId = '';

    public string $tipoEquipoId = '';

    public string $departamentoId = '';

    public string $marcaId = '';

    public string $modelo = '';

    public string $color = '';

    public string $numeroSerie = '';

    public string $numeroInventario = '';

    public string $observaciones = '';

    public const COLUMNS = EquiposTable::COLUMNS;

    public array $visibleColumns = ['numero_inventario', 'tipo_equipo', 'marca', 'numero_serie', 'departamento', 'disponibilidad'];

    private const DEFAULT_FILTERS = ['categoria' => '', 'tipo' => '', 'marca' => '', 'departamento' => '', 'estado' => ''];

    public array $filters = self::DEFAULT_FILTERS;

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function setVisibleColumns(array $columns): void
    {
        $columns = array_values(array_intersect(array_keys(self::COLUMNS), $columns));
        if ($columns !== []) {
            $this->visibleColumns = $columns;
        }
    }

    public function toggleColumn(string $column): void
    {
        abort_unless(array_key_exists($column, self::COLUMNS), 422);
        $columns = $this->visibleColumns;
        if (in_array($column, $columns, true)) {
            $columns = array_values(array_diff($columns, [$column]));
        } else {
            $columns[] = $column;
        }
        $this->setVisibleColumns($columns);
    }

    public function clearFilters(): void
    {
        $this->reset('desde', 'hasta');
        $this->buscar = '';
        $this->filters = self::DEFAULT_FILTERS;
        $this->resetPage();
    }

    public function removeFilter(string $filter): void
    {
        abort_unless(array_key_exists($filter, self::DEFAULT_FILTERS), 422);

        $this->filters[$filter] = self::DEFAULT_FILTERS[$filter];
        $this->resetPage();
    }

    public string $sortColumn = '';

    public string $sortDirection = '';

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

    public function cambiarEstado(int $id): void
    {
        abort_unless(Auth::user()?->activo, 403);
        $this->resetValidation('estadoEquipo');

        DB::transaction(function () use ($id): void {
            $equipo = Equipo::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $anterior = (bool) $equipo->activo;

            if ($anterior && $equipo->prestamos()->where('estado', 'prestado')->exists()) {
                throw ValidationException::withMessages([
                    'estadoEquipo' => 'No puede desactivar un equipo que tiene un préstamo pendiente.',
                ]);
            }

            $equipo->activo = ! $anterior;
            $equipo->save();
            RegistrarAuditoria::cambioEstado($equipo, $anterior, ! $anterior);
            session()->flash('mensaje', $anterior ? 'Equipo desactivado correctamente.' : 'Equipo activado correctamente.');
        }, 3);
        $this->resetPage();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function verEquipo(int $id): void
    {
        abort_unless(Auth::user()?->activo, 403);

        $this->equipoDetalleId = Equipo::query()->findOrFail($id, ['id'])->id;
        $this->resetPage('movimientosEquipo');
        $this->resetPage('ingresosEquipo');
    }

    public function cerrarDetalle(): void
    {
        $this->equipoDetalleId = null;
        $this->resetPage('movimientosEquipo');
        $this->resetPage('ingresosEquipo');
    }

    public function crearEquipo(): void
    {
        $this->cerrarDetalle();
        $this->resetFormulario();

        $this->mostrarModal = true;
    }

    public function editarEquipo(int $id): void
    {
        $equipo = Equipo::findOrFail($id);
        $this->cerrarDetalle();

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

    public function updatedCategoriaId(): void
    {
        $categoria = $this->categoriaActiva();
        $this->resetValidation('categoriaId');
        $this->limpiarMarcaPorCategoria($categoria);

        if ($this->tipoEquipoId !== '' && ! $this->opcionDisponible(TipoEquipo::class, $categoria, $this->tipoEquipoId)) {
            $this->tipoEquipoId = '';
        }

        $this->resetValidation('tipoEquipoId');
    }

    public function abrirModalCatalogo(string $tipo): void
    {
        if (! RegistrarCatalogoEquipo::permite($tipo)) {
            return;
        }

        $this->reset('vincularExistente', 'catalogoExistenteId');
        $this->tipoCatalogo = $tipo;
        $this->nuevoNombreCatalogo = '';
        $this->nuevaSiglaDepartamento = '';

        $this->resetValidation();

        $this->mostrarModalCatalogo = true;
    }

    public function cerrarModalCatalogo(): void
    {
        $this->reset('vincularExistente', 'catalogoExistenteId');
        $this->mostrarModalCatalogo = false;
        $this->tipoCatalogo = '';
        $this->nuevoNombreCatalogo = '';
        $this->nuevaSiglaDepartamento = '';
        $this->resetValidation();
    }

    public function guardarCatalogoRapido(RegistrarCatalogoEquipo $registro): void
    {
        abort_unless($this->mostrarModalCatalogo && RegistrarCatalogoEquipo::permite($this->tipoCatalogo), 422);
        $vincular = $this->vincularExistente && RegistrarCatalogoEquipo::esVinculable($this->tipoCatalogo);
        $this->validate($registro->reglas($this->tipoCatalogo, $vincular), $registro->mensajes());

        if ($vincular) {
            abort_unless($this->mostrarModal, 422);
            $opcion = $registro->vincular($this->tipoCatalogo, (int) $this->catalogoExistenteId, $this->categoriaId);
        } else {
            $opcion = $registro->crear(
                $this->tipoCatalogo, $this->nuevoNombreCatalogo, $this->nuevaSiglaDepartamento, $this->categoriaId, (int) Auth::id(),
            );
        }

        $propiedad = match ($this->tipoCatalogo) {
            'categoria' => 'categoriaId',
            'tipo_equipo' => 'tipoEquipoId',
            'marca' => 'marcaId',
            'departamento' => 'departamentoId',
        };
        $this->{$propiedad} = (string) $opcion->id;
        if ($this->tipoCatalogo === 'categoria') {
            $this->updatedCategoriaId();
        }

        $this->cerrarModalCatalogo();
        session()->flash('mensajeCatalogo', $vincular ? 'Opción vinculada y seleccionada correctamente.' : 'Registro creado y seleccionado correctamente.');
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
        $reglas = [];
        foreach (RegistrarEquipo::reglas(alta: $this->equipoId === null) as $campo => $regla) {
            $reglas[self::CAMPOS_EQUIPO[$campo]] = $regla;
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'categoriaId.required' => 'Debe seleccionar una categoría.',

            'categoriaId.exists' => 'La categoría seleccionada no es válida.',
            'categoriaId.integer' => 'La categoría seleccionada no es válida.',

            'tipoEquipoId.required' => 'Debe seleccionar un tipo de equipo.',

            'tipoEquipoId.exists' => 'El tipo de equipo seleccionado no es válido.',
            'tipoEquipoId.integer' => 'El tipo de equipo seleccionado no es válido.',

            'departamentoId.required' => 'Debe seleccionar el departamento de procedencia.',

            'departamentoId.exists' => 'El departamento seleccionado no es válido.',

            'marcaId.required' => 'Debe seleccionar una marca.',

            'marcaId.exists' => 'La marca seleccionada no es válida.',
            'marcaId.integer' => 'La marca seleccionada no es válida.',

            'modelo.required' => 'El modelo es obligatorio.',
            'modelo.max' => 'El modelo no puede superar los 100 caracteres.',

            'color.required' => 'El color es obligatorio.',
            'color.max' => 'El color no puede superar los 50 caracteres.',

            'numeroSerie.required' => 'El número de serie es obligatorio.',
            'numeroSerie.max' => 'El número de serie no puede superar los 100 caracteres.',

            'numeroInventario.required' => 'El número de inventario es obligatorio.',

            'numeroInventario.max' => 'El número de inventario no puede superar los 100 caracteres.',

            'observaciones.required' => 'Las observaciones son obligatorias.',
            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    public function guardarEquipo(RegistrarEquipo $registro): void
    {
        $this->validate();

        $datos = [];
        foreach (self::CAMPOS_EQUIPO as $campo => $propiedad) {
            $datos[$campo] = $this->{$propiedad};
        }
        $datos = RegistrarEquipo::normalizar($datos);
        $esCreacion = $this->equipoId === null;

        try {
            if ($esCreacion) {
                $registro->crear($datos, (int) Auth::id());
            } else {
                DB::transaction(function () use ($registro, $datos): void {
                    $equipo = Equipo::query()->lockForUpdate()->findOrFail($this->equipoId);

                    $categoria = $this->validarCategoriaYMarca($equipo);
                    if (! $this->conservaOpcion($equipo, 'tipo_equipo_id', $this->tipoEquipoId)
                        && ! $this->opcionDisponible(TipoEquipo::class, $categoria, $this->tipoEquipoId, bloquear: true)) {
                        throw ValidationException::withMessages([
                            'tipoEquipoId' => 'Seleccione un tipo de equipo activo vinculado a la categoría.',
                        ]);
                    }

                    $registro->validarIdentificadores($datos['numero_inventario'], $datos['numero_serie'], $equipo->id);
                    $equipo->fill($datos);
                    RegistrarAuditoria::guardarActualizacion($equipo);
                }, 3);
            }
        } catch (ValidationException $exception) {
            throw RegistrarEquipo::adaptarErrores($exception, self::CAMPOS_EQUIPO);
        }

        session()->flash(
            'mensaje',
            $esCreacion ? 'Equipo registrado correctamente.' : 'Equipo actualizado correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }

    public function render(HistorialMovimientosTable $historial): View
    {
        $dateRange = ['desde' => $this->desde, 'hasta' => $this->hasta];
        $dateErrors = Validator::make($dateRange, [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->desde !== '' ? ['after_or_equal:desde'] : [])],
        ], MovimientosTable::FILTER_MESSAGES)->errors();
        $queryDates = $dateErrors->isEmpty() ? $dateRange : [];

        $table = app(EquiposTable::class);
        $tableFilters = $table->validate(array_merge($this->filters, [
            'buscar' => $this->buscar,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
            'columns' => $this->visibleColumns,
            ...$queryDates,
        ]));
        $equipos = $table->query($tableFilters)->when($dateErrors->isNotEmpty(), fn ($query) => $query->whereRaw('1 = 0'))->paginate(10);
        $filterOptions = [
            'categoria' => Categoria::query()->where('aplica_a', 'equipo')->orderBy('nombre')->get(),
            'tipo' => TipoEquipo::query()->orderBy('nombre')->get(),
            'marca' => Marca::query()->orderBy('nombre')->get(),
            'departamento' => Departamento::query()->orderBy('nombre')->get(),
        ];

        $equipoDetalle = null;
        $movimientosEquipo = null;
        $ingresosEquipo = null;
        if ($this->equipoDetalleId !== null) {
            abort_unless(Auth::user()?->activo, 403);

            $equipoDetalle = $table->query([])->with('creador:id,name')->find($this->equipoDetalleId);
            if ($equipoDetalle !== null) {
                $movimientosEquipo = $historial->equipmentTimeline($equipoDetalle->id)->paginate(10, pageName: 'movimientosEquipo');
                if (Gate::allows('acceder-taller')) {
                    $ingresosEquipo = IngresoTaller::query()->historialEquipo($equipoDetalle->id)->paginate(6, pageName: 'ingresosEquipo');
                }
            }
        }

        $registro = $this->mostrarModal && $this->equipoId !== null
            ? Equipo::find($this->equipoId)
            : null;
        $categoria = $this->mostrarModal ? $this->categoriaActiva() : null;
        $categoriaCatalogo = $categoria;
        $catalogoVinculable = $this->modeloCatalogoVinculable() !== null;
        $opcionesCatalogoExistente = $this->opcionesCatalogoExistente($categoria);
        $categoriaDisponible = $categoria !== null;
        $categorias = $this->categoriasDelFormulario($registro);

        $tiposEquipo = $this->opcionesDelCatalogo(
            TipoEquipo::class,
            $categoria,
            $this->conservaOpcion($registro, 'tipo_equipo_id', $this->tipoEquipoId),
            $this->tipoEquipoId,
        );

        $departamentos = Departamento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $marcas = $this->opcionesDelCatalogo(
            Marca::class,
            $categoria,
            $this->conservaOpcion($registro, 'marca_id', $this->marcaId),
            $this->marcaId,
        );

        return view(
            'livewire.inventario.equipos.index',
            compact(
                'equipos',
                'equipoDetalle',
                'movimientosEquipo',
                'ingresosEquipo',
                'dateErrors',
                'filterOptions',
                'categorias',
                'categoriaDisponible',
                'categoriaCatalogo',
                'catalogoVinculable',
                'opcionesCatalogoExistente',
                'tiposEquipo',
                'departamentos',
                'marcas'
            )
        );
    }
}
