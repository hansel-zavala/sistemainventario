<?php

namespace App\Livewire\Inventario\Insumos;

use App\Models\Categoria;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\UnidadMedida;
use App\Models\MovimientoInventario;
use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\RegistrarAperturaInventario;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public bool $mostrarHistorial = false;

    public ?int $historialInsumoId = null;

    public string $historialInsumoNombre = '';

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

            /* Validar unidad de medida */

            $unidad = UnidadMedida::findOrFail(
                $this->unidadMedidaId
            );

            if (! $unidad->permite_decimales) {

                if (fmod($stockMinimo, 1.0) !== 0.0) {
                    throw ValidationException::withMessages([
                        'stockMinimo' =>
                        'La unidad de medida no permite un stock mínimo con decimales.',
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
                        'unidadMedidaId' =>
                        'No puede utilizar esta unidad de medida porque la existencia contiene decimales.',
                    ]);
                }
            }

            /* Datos generales del insumo */

            $datos = [
                'nombre' => trim($this->nombre),

                'categoria_id' =>
                (int) $this->categoriaId,

                'marca_id' =>
                $this->marcaId !== ''
                    ? (int) $this->marcaId
                    : null,

                'unidad_medida_id' =>
                (int) $this->unidadMedidaId,

                'stock_minimo' =>
                $stockMinimo,

                'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

                'activo' =>
                $this->activoInsumo,
            ];

            /* Crear insumo */

            if ($esCreacion) {

                $datos['cantidad_actual'] = $cantidadInicial;

                $datos['creado_por'] = Auth::id();

                $insumo = Insumo::create($datos);

                /* Registrar apertura inicial */

                RegistrarAperturaInventario::registrar(
                    'insumo',
                    $insumo->id,
                    $cantidadInicial,
                    'Apertura inicial del insumo: ' . $insumo->nombre
                );

                /* Registrar auditoría de creación */

                Auditoria::create([
                    'modelo' => Insumo::class,

                    'registro_id' => $insumo->id,

                    'accion' => 'creacion',

                    'valores_anteriores' => null,

                    'valores_nuevos' => $insumo->only([
                        'nombre',
                        'categoria_id',
                        'marca_id',
                        'unidad_medida_id',
                        'cantidad_actual',
                        'stock_minimo',
                        'observaciones',
                        'activo',
                    ]),

                    'usuario_id' => Auth::id(),
                ]);
            } else {

                /* Preparar los cambios administrativos */

                $insumo->fill($datos);

                $valoresNuevos = $insumo->getDirty();

                /* Guardar solamente si hay cambios */

                if (! empty($valoresNuevos)) {

                    $valoresAnteriores = [];

                    foreach ($valoresNuevos as $campo => $valor) {

                        $valoresAnteriores[$campo] =
                            $insumo->getOriginal($campo);
                    }

                    /* Guardar los datos actualizados. */
                    $insumo->save();

                    /* Registrar auditoría de modificación */

                    Auditoria::create([
                        'modelo' => Insumo::class,

                        'registro_id' => $insumo->id,

                        'accion' => 'actualizacion',

                        'valores_anteriores' => $valoresAnteriores,

                        'valores_nuevos' => $valoresNuevos,

                        'usuario_id' => Auth::id(),
                    ]);
                }
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

            /* Obtener y bloquear el insumo */

            $insumo = Insumo::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            /* Identificar los estados */

            $estadoAnterior = (bool) $insumo->activo;

            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar el estado del insumo */

            $insumo->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar la auditoría */

            Auditoria::create([
                'modelo' => Insumo::class,

                'registro_id' => $insumo->id,

                'accion' => $nuevoEstado
                    ? 'activacion'
                    : 'desactivacion',

                'valores_anteriores' => [
                    'activo' => $estadoAnterior,
                ],

                'valores_nuevos' => [
                    'activo' => $nuevoEstado,
                ],

                'usuario_id' => Auth::id(),
            ]);
            return $nuevoEstado;
        }, 3);

        /* Mensaje de confirmación */

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Insumo activado correctamente.'
                : 'Insumo desactivado correctamente.'
        );
    }



    public function abrirHistorial(int $id): void
    {
        $insumo = Insumo::findOrFail($id);

        $this->historialInsumoId = $insumo->id;
        $this->historialInsumoNombre = $insumo->nombre;

        $this->mostrarHistorial = true;
    }

    public function cerrarHistorial(): void
    {
        $this->mostrarHistorial = false;
        $this->historialInsumoId = null;
        $this->historialInsumoNombre = '';
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


        // Historial del insumo seleccionado. 
        $movimientosHistorial = collect();

        if ($this->mostrarHistorial && $this->historialInsumoId !== null) {

            $insumoId = $this->historialInsumoId;

            $movimientosHistorial = MovimientoInventario::query()
                ->with([
                    'usuario',
                    'detalles' => function ($query) use ($insumoId) {
                        $query->where('tipo_item', 'insumo')
                            ->where('item_id', $insumoId);
                    },
                ])
                ->whereIn('tipo', [
                    'apertura',
                    'entrada',
                    'salida',
                ])
                ->whereHas('detalles', function ($query) use ($insumoId) {
                    $query->where('tipo_item', 'insumo')
                        ->where('item_id', $insumoId);
                })
                ->orderByDesc('fecha_movimiento')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view(
            'livewire.inventario.insumos.index',
            compact(
                'insumos',
                'categorias',
                'marcas',
                'unidades',
                'movimientosHistorial',
            )
        );
    }
}
