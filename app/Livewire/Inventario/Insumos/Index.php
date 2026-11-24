<?php

namespace App\Livewire\Inventario\Insumos;

use App\Livewire\Inventario\CatalogosPorCategoria;
use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\UnidadMedida;
use App\Services\HistorialMovimientosTable;
use App\Services\InsumosTable;
use App\Services\MovimientosTable;
use App\Services\RegistrarAperturaInventario;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    private const TIPO_CATEGORIA = 'insumo';

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

    public const COLUMNS = InsumosTable::COLUMNS;

    public array $visibleColumns = ['nombre', 'categoria', 'marca', 'cantidad_actual', 'stock_minimo', 'activo'];

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
        $this->reset('desde', 'hasta');
        $this->reset('buscar', 'filtroEstado');
        $this->resetPage();
    }

    public function removeStatusFilter(): void
    {
        $this->reset('filtroEstado');
        $this->resetPage();
    }

    #[Locked]
    public ?int $insumoDetalleId = null;

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
        $this->cerrarDetalle();
        $this->resetFormulario();

        $this->mostrarModal = true;
    }

    public function editarInsumo(int $id): void
    {
        $insumo = Insumo::findOrFail($id);
        $this->cerrarDetalle();

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

    public function updatedCategoriaId(): void
    {
        $categoria = $this->categoriaActiva();
        $this->resetValidation('categoriaId');
        $this->limpiarMarcaPorCategoria($categoria);
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

        $this->reset('vincularExistente', 'catalogoExistenteId');
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
        $this->reset('vincularExistente', 'catalogoExistenteId');
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
            'nuevoNombreCatalogo.required' => $this->tipoCatalogo === 'unidad_medida'
                ? 'El nombre en singular es obligatorio.'
                : 'El nombre es obligatorio.',

            'nuevoNombreCatalogo.max' => $this->tipoCatalogo === 'unidad_medida'
                ? 'El nombre en singular no puede superar los 50 caracteres.'
                : 'El nombre no puede superar los 150 caracteres.',

            'nuevoNombrePluralUnidad.required' => 'El nombre en plural es obligatorio.',

            'nuevoNombrePluralUnidad.max' => 'El nombre en plural no puede superar los 50 caracteres.',

            'nuevaAbreviaturaUnidad.max' => 'La abreviatura no puede superar los 15 caracteres.',
        ];
    }

    public function guardarCatalogoRapido(): void
    {
        if ($this->vincularExistente && $this->modeloCatalogoVinculable() !== null) {
            $this->vincularCatalogoRapido();

            return;
        }

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

            $categoria = DB::transaction(function () use ($nombre) {

                $categoria = Categoria::create([
                    'nombre' => $nombre,
                    'aplica_a' => 'insumo',
                    'activo' => true,
                    'creado_por' => Auth::id(),
                ]);

                RegistrarAuditoria::creacion($categoria, [
                    'nombre',
                    'aplica_a',
                    'activo',
                ]);

                return $categoria;
            }, 3);

            $this->categoriaId = (string) $categoria->id;
            $this->updatedCategoriaId();
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
                    'Ya existe una marca con este nombre. Elija «Vincular existente» para usarla.'
                );

                return;
            }

            $marca = DB::transaction(function () use ($nombre) {
                $categoria = $this->exigirCategoriaParaAltaRapida();
                $marca = Marca::create([
                    'nombre' => $nombre,
                    'activo' => true,
                    'creado_por' => Auth::id(),
                ]);
                $marca->categorias()->attach($categoria->id);

                RegistrarAuditoria::creacion($marca, [
                    'nombre',
                    'activo',
                ]);

                return $marca;
            }, 3);

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

            $unidad = DB::transaction(function () use (
                $singular,
                $plural,
                $abreviatura
            ) {

                $unidad = UnidadMedida::create([
                    'nombre_singular' => $singular,
                    'nombre_plural' => $plural,

                    'abreviatura' => $abreviatura !== ''
                        ? $abreviatura
                        : null,

                    'permite_decimales' => $this->nuevoPermiteDecimalesUnidad,

                    'activo' => true,
                    'creado_por' => Auth::id(),
                ]);

                RegistrarAuditoria::creacion($unidad, [
                    'nombre_singular',
                    'nombre_plural',
                    'abreviatura',
                    'permite_decimales',
                    'activo',
                ]);

                return $unidad;
            }, 3);

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
                'bail',
                'required',
                'integer',
                'exists:categorias,id',
            ],

            'marcaId' => [
                'bail',
                'nullable',
                'integer',
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
            'nombre.required' => 'El nombre del insumo es obligatorio.',

            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',

            'categoriaId.required' => 'Debe seleccionar una categoría.',

            'categoriaId.exists' => 'La categoría seleccionada no es válida.',
            'categoriaId.integer' => 'La categoría seleccionada no es válida.',

            'marcaId.exists' => 'La marca seleccionada no es válida.',
            'marcaId.integer' => 'La marca seleccionada no es válida.',

            'unidadMedidaId.required' => 'Debe seleccionar una unidad de medida.',

            'unidadMedidaId.exists' => 'La unidad de medida seleccionada no es válida.',

            'cantidadActual.required' => 'La cantidad actual es obligatoria.',

            'cantidadActual.numeric' => 'La cantidad actual debe ser un número.',

            'cantidadActual.min' => 'La cantidad actual no puede ser negativa.',

            'stockMinimo.required' => 'El stock mínimo es obligatorio.',

            'stockMinimo.numeric' => 'El stock mínimo debe ser un número.',

            'stockMinimo.min' => 'El stock mínimo no puede ser negativo.',

            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    public function guardarInsumo(): void
    {
        $this->validate();

        $esCreacion = $this->insumoId === null;

        $cantidadInicial = (float) $this->cantidadActual;
        $stockMinimo = (float) $this->stockMinimo;

        DB::transaction(function () use (
            $esCreacion,
            $cantidadInicial,
            $stockMinimo
        ) {
            /* Obtener y bloquear el insumo si estamos editando */

            $insumo = null;

            if (! $esCreacion) {
                $insumo = Insumo::query()
                    ->whereKey($this->insumoId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $this->validarCategoriaYMarca($insumo);

            /* Validar unidad de medida */

            $unidad = UnidadMedida::findOrFail(
                $this->unidadMedidaId
            );

            if (! $unidad->permite_decimales) {

                if (fmod($stockMinimo, 1.0) !== 0.0) {
                    throw ValidationException::withMessages([
                        'stockMinimo' => 'La unidad de medida no permite un stock mínimo con decimales.',
                    ]);
                }

                /*
                | Al crear validamos la cantidad inicial.
                | Al editar validamos la existencia REAL de la BD.
                */
                $cantidadAValidar = $esCreacion
                    ? $cantidadInicial
                    : (float) $insumo->cantidad_actual;

                if (fmod($cantidadAValidar, 1.0) !== 0.0) {
                    throw ValidationException::withMessages([
                        'unidadMedidaId' => 'No puede utilizar esta unidad de medida porque la existencia contiene decimales.',
                    ]);
                }
            }

            /* Datos generales del insumo */

            $datos = [
                'nombre' => trim($this->nombre),

                'categoria_id' => (int) $this->categoriaId,

                'marca_id' => $this->marcaId !== ''
                    ? (int) $this->marcaId
                    : null,

                'unidad_medida_id' => (int) $this->unidadMedidaId,

                'stock_minimo' => $stockMinimo,

                'observaciones' => trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

                'activo' => $this->activoInsumo,
            ];

            /* Crear insumo */

            if ($esCreacion) {

                $datos['cantidad_actual'] = $cantidadInicial;
                $datos['creado_por'] = Auth::id();

                $insumo = Insumo::create($datos);

                RegistrarAperturaInventario::registrar(
                    'insumo',
                    $insumo->id,
                    $cantidadInicial,
                    'Apertura inicial del insumo: '.$insumo->nombre
                );

                RegistrarAuditoria::creacion($insumo, [
                    'nombre',
                    'categoria_id',
                    'marca_id',
                    'unidad_medida_id',
                    'cantidad_actual',
                    'stock_minimo',
                    'observaciones',
                    'activo',
                ]);
            } else {

                $insumo->fill($datos);

                RegistrarAuditoria::guardarActualizacion($insumo);
            }
        }, 3);

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Insumo registrado correctamente.'
                : 'Insumo actualizado correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }

    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            /* Obtener y bloquear el insumo. */
            $insumo = Insumo::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $insumo->activo;
            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar estado. */
            $insumo->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar mediante el servicio compartido. */
            RegistrarAuditoria::cambioEstado(
                $insumo,
                $estadoAnterior,
                $nuevoEstado
            );

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Insumo activado correctamente.'
                : 'Insumo desactivado correctamente.'
        );
    }

    public function verInsumo(int $id): void
    {
        abort_unless(Auth::user()?->activo, 403);

        $this->insumoDetalleId = Insumo::query()->findOrFail($id, ['id'])->id;
        $this->resetPage('movimientosInsumo');
    }

    public function cerrarDetalle(): void
    {
        $this->insumoDetalleId = null;
        $this->resetPage('movimientosInsumo');
    }

    public function render(InsumosTable $table, HistorialMovimientosTable $historial): View
    {
        $dateRange = ['desde' => $this->desde, 'hasta' => $this->hasta];
        $dateErrors = Validator::make($dateRange, [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->desde !== '' ? ['after_or_equal:desde'] : [])],
        ], MovimientosTable::FILTER_MESSAGES)->errors();
        $queryDates = $dateErrors->isEmpty() ? $dateRange : [];

        $insumos = $table->query([
            ...$queryDates,
            'buscar' => $this->buscar, 'filtroEstado' => $this->filtroEstado,
            'sortColumn' => $this->sortColumn, 'sortDirection' => $this->sortDirection,
        ])->when($dateErrors->isNotEmpty(), fn ($query) => $query->whereRaw('1 = 0'))->paginate(10);

        $registro = $this->mostrarModal && $this->insumoId !== null
            ? Insumo::find($this->insumoId)
            : null;
        $categoria = $this->mostrarModal ? $this->categoriaActiva() : null;
        $categoriaCatalogo = $categoria;
        $catalogoVinculable = $this->modeloCatalogoVinculable() !== null;
        $opcionesCatalogoExistente = $this->opcionesCatalogoExistente($categoria);
        $categoriaDisponible = $categoria !== null;
        $categorias = $this->categoriasDelFormulario($registro);

        $marcas = $this->opcionesDelCatalogo(
            Marca::class,
            $categoria,
            $this->conservaOpcion($registro, 'marca_id', $this->marcaId),
            $this->marcaId,
        );

        $unidades = UnidadMedida::query()
            ->where('activo', true)
            ->orderBy('nombre_singular')
            ->get();

        $insumoDetalle = null;
        $movimientosInsumo = null;
        if ($this->insumoDetalleId !== null) {
            abort_unless(Auth::user()?->activo, 403);

            $insumoDetalle = $table->query([])->with('creador:id,name')->find($this->insumoDetalleId);
            if ($insumoDetalle !== null) {
                $movimientosInsumo = $historial->supplyTimeline($insumoDetalle->id);
            }
        }

        return view(
            'livewire.inventario.insumos.index',
            compact(
                'insumos',
                'dateErrors',
                'categorias',
                'categoriaDisponible',
                'categoriaCatalogo',
                'catalogoVinculable',
                'opcionesCatalogoExistente',
                'marcas',
                'unidades',
                'insumoDetalle',
                'movimientosInsumo',
            )
        );
    }
}
