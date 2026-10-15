<?php

namespace App\Livewire\Inventario\Herramientas;

use App\Models\Categoria;
use App\Models\Herramienta;
use App\Models\PrestamoDetalle;
use App\Models\Marca;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroTipo = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public bool $mostrarModalCatalogo = false;

    public string $tipoCatalogo = '';

    public string $nuevoNombreCatalogo = '';

    public string $nuevoNombrePluralUnidad = '';

    public string $nuevaAbreviaturaUnidad = '';

    public bool $nuevoPermiteDecimalesUnidad = false;

    public ?int $herramientaId = null;

    public string $nombre = '';

    public string $categoriaId = '';

    public string $marcaId = '';

    public string $unidadMedidaId = '';

    public string $tipoControl = 'individual';

    public string $codigoInterno = '';

    public string $cantidadActual = '1';

    public string $stockMinimo = '0';

    public string $observaciones = '';

    public bool $activoHerramienta = true;

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedTipoControl(): void
    {
        if ($this->herramientaId !== null) {
            return;
        }

        if ($this->tipoControl === 'individual') {
            $this->cantidadActual = '1';
            $this->stockMinimo = '0';
        }
    }

    public function crearHerramienta(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }

    public function editarHerramienta(int $id): void
    {
        $herramienta = Herramienta::findOrFail($id);

        $this->herramientaId = $herramienta->id;
        $this->nombre = $herramienta->nombre;
        $this->categoriaId = (string) $herramienta->categoria_id;
        $this->marcaId = $herramienta->marca_id
            ? (string) $herramienta->marca_id
            : '';
        $this->unidadMedidaId = (string) $herramienta->unidad_medida_id;
        $this->tipoControl = $herramienta->tipo_control;
        $this->codigoInterno = $herramienta->codigo_interno ?? '';
        $this->cantidadActual = (string) $herramienta->cantidad_actual;
        $this->stockMinimo = (string) $herramienta->stock_minimo;
        $this->observaciones = $herramienta->observaciones ?? '';
        $this->activoHerramienta = $herramienta->activo;

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
        $this->validate(
            $this->rulesCatalogoRapido(),
            $this->messagesCatalogoRapido()
        );

        $nombre = trim($this->nuevoNombreCatalogo);

        if ($this->tipoCatalogo === 'categoria') {

            $duplicada = Categoria::query()
                ->where('aplica_a', 'herramienta')
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->exists();

            if ($duplicada) {
                $this->addError(
                    'nuevoNombreCatalogo',
                    'Ya existe una categoría de herramientas con este nombre.'
                );

                return;
            }

            $categoria = Categoria::create([
                'nombre' => $nombre,
                'aplica_a' => 'herramienta',
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
        $this->herramientaId = null;
        $this->nombre = '';
        $this->categoriaId = '';
        $this->marcaId = '';
        $this->unidadMedidaId = '';
        $this->tipoControl = 'individual';
        $this->codigoInterno = '';
        $this->cantidadActual = '1';
        $this->stockMinimo = '0';
        $this->observaciones = '';
        $this->activoHerramienta = true;
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

            'tipoControl' => [
                'required',
                'in:individual,cantidad',
            ],

            'codigoInterno' => [
                $this->tipoControl === 'individual'
                    ? 'required'
                    : 'nullable',
                'string',
                'max:100',
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

            'activoHerramienta' => [
                'boolean',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la herramienta es obligatorio.',

            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',

            'categoriaId.required' => 'Debe seleccionar una categoría.',

            'categoriaId.exists' => 'La categoría seleccionada no es válida.',

            'marcaId.exists' => 'La marca seleccionada no es válida.',

            'unidadMedidaId.required' => 'Debe seleccionar una unidad de medida.',

            'unidadMedidaId.exists' => 'La unidad de medida seleccionada no es válida.',

            'tipoControl.required' => 'Debe seleccionar el tipo de control.',

            'tipoControl.in' => 'El tipo de control seleccionado no es válido.',

            'codigoInterno.required' => 'El código interno es obligatorio para herramientas individuales.',

            'codigoInterno.max' => 'El código interno no puede superar los 100 caracteres.',

            'cantidadActual.required' => 'La cantidad actual es obligatoria.',

            'cantidadActual.numeric' => 'La cantidad actual debe ser un número.',

            'cantidadActual.min' => 'La cantidad actual no puede ser negativa.',

            'stockMinimo.required' => 'El stock mínimo es obligatorio.',

            'stockMinimo.numeric' => 'El stock mínimo debe ser un número.',

            'stockMinimo.min' => 'El stock mínimo no puede ser negativo.',

            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    
public function guardarHerramienta(): void
{
    $this->validate();

    $codigo = trim($this->codigoInterno);

    // El stock mínimo puede editarse.
    $stockMinimo = (float) $this->stockMinimo;

    $esCreacion = $this->herramientaId === null;

    // Esta cantidad solo se utilizará al crear.
    $cantidadInicial = $this->tipoControl === 'individual'
        ? 1.0
        : (float) $this->cantidadActual;

    DB::transaction(function () use (
        $codigo,
        $stockMinimo,
        $esCreacion,
        $cantidadInicial
    ) {
        /*
         * Bloquear el registro durante la edición.
         */
        $herramienta = null;

        if (! $esCreacion) {
            $herramienta = Herramienta::query()
                ->whereKey($this->herramientaId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        /*
         * Validar código interno único.
         */
        if ($this->tipoControl === 'individual') {
            $codigoDuplicado = Herramienta::query()
                ->whereRaw(
                    'LOWER(TRIM(codigo_interno)) = ?',
                    [mb_strtolower($codigo)]
                )
                ->when(
                    ! $esCreacion,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $this->herramientaId
                    )
                )
                ->exists();

            if ($codigoDuplicado) {
                throw ValidationException::withMessages([
                    'codigoInterno' =>
                        'Ya existe una herramienta con este código interno.',
                ]);
            }
        }

        /*
         * Validar unidad de medida.
         */
        $unidad = UnidadMedida::findOrFail(
            $this->unidadMedidaId
        );

        if (! $unidad->permite_decimales) {
            if (
                fmod($stockMinimo, 1.0) !== 0.0
                || (
                    $esCreacion
                    && fmod($cantidadInicial, 1.0) !== 0.0
                )
            ) {
                throw ValidationException::withMessages([
                    'stockMinimo' =>
                        'La unidad de medida no permite cantidades decimales.',
                ]);
            }
        }

        /*
         * Comprobar préstamos pendientes.
         */
        $cantidadPrestada = 0.0;

        if (! $esCreacion) {
            /*
            |--------------------------------------------------------------------------
            | Impedir cambiar el tipo de control
            |--------------------------------------------------------------------------
            */
            if ($this->tipoControl !== $herramienta->tipo_control) {
                throw ValidationException::withMessages([
                    'tipoControl' =>
                        'El tipo de control no puede modificarse después del registro de la herramienta.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Consultar las cantidades pendientes de devolución
            |--------------------------------------------------------------------------
            */
            $cantidadPrestada = (float) PrestamoDetalle::query()
                ->where('herramienta_id', $herramienta->id)
                ->where('estado', 'prestado')
                ->selectRaw(
                    'COALESCE(SUM(cantidad_prestada - cantidad_devuelta), 0) AS pendiente'
                )
                ->value('pendiente');


            /*
             * No permitir desactivar herramientas prestadas.
             */
            if (
                $cantidadPrestada > 0
                && ! $this->activoHerramienta
            ) {
                throw ValidationException::withMessages([
                    'activoHerramienta' =>
                        'No puede desactivar una herramienta que tiene préstamos pendientes.',
                ]);
            }

            /*
             * No permitir cambiar la unidad a una que
             * no admite decimales si hay existencias
             * o préstamos fraccionarios.
             */
            if (
                ! $unidad->permite_decimales
                && (
                    fmod(
                        (float) $herramienta->cantidad_actual,
                        1.0
                    ) !== 0.0
                    || fmod($cantidadPrestada, 1.0) !== 0.0
                )
            ) {
                throw ValidationException::withMessages([
                    'unidadMedidaId' =>
                        'La existencia o los préstamos pendientes contienen decimales incompatibles con esta unidad.',
                ]);
            }
        }

        /*
         * Datos generales.
         * IMPORTANTE: cantidad_actual no se incluye
         * al editar una herramienta.
         */
        $datos = [
            'nombre' => trim($this->nombre),

            'categoria_id' => (int) $this->categoriaId,

            'marca_id' => $this->marcaId !== ''
                ? (int) $this->marcaId
                : null,

            'unidad_medida_id' =>
                (int) $this->unidadMedidaId,

            'tipo_control' => $this->tipoControl,

            'codigo_interno' => $codigo !== ''
                ? $codigo
                : null,

            'stock_minimo' =>
                $this->tipoControl === 'individual'
                    ? 0
                    : $stockMinimo,

            'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

            'activo' => $this->activoHerramienta,
        ];

        if ($esCreacion) {
            /*
             * La existencia inicial solamente se
             * establece al registrar por primera vez.
             */
            $datos['cantidad_actual'] = $cantidadInicial;
            $datos['creado_por'] = Auth::id();

            Herramienta::create($datos);
        } else {
            /*
             * La existencia se conserva exactamente
             * como está en la base de datos.
             */
            $herramienta->update($datos);
        }
    }, 3);

    session()->flash(
        'mensaje',
        $esCreacion
            ? 'Herramienta registrada correctamente.'
            : 'Herramienta actualizada correctamente.'
    );

    $this->cerrarModal();
    $this->resetPage();
}


    
    public function cambiarEstado(int $id): void
    {
        DB::transaction(function () use ($id) {

            $herramienta = Herramienta::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            // Si está activa, se intenta desactivar.
            $desactivar = (bool) $herramienta->activo;

            if ($desactivar) {

                $tienePrestamosPendientes = PrestamoDetalle::query()
                    ->where('herramienta_id', $herramienta->id)
                    ->where('estado', 'prestado')
                    ->whereColumn(
                        'cantidad_devuelta',
                        '<',
                        'cantidad_prestada'
                    )
                    ->exists();

                if ($tienePrestamosPendientes) {
                    throw ValidationException::withMessages([
                        'estadoHerramienta' =>
                            'No puede desactivar esta herramienta porque tiene préstamos pendientes de devolución.',
                    ]);
                }
            }

            $herramienta->update([
                'activo' => ! $herramienta->activo,
            ]);

            session()->flash(
                'mensaje',
                $herramienta->activo
                    ? 'Herramienta activada correctamente.'
                    : 'Herramienta desactivada correctamente.'
            );

        }, 3);
    }


    
public function render()
{
    $herramientas = Herramienta::query()
        ->withExists([
            'prestamos as esta_prestada' => function ($query) {
                $query->where('estado', 'prestado');
            },
        ])
        ->with([
            'categoria',
            'marca',
            'unidadMedida',
        ])
        ->when(
            $this->buscar,
            function ($query) {
                $buscar = '%' . trim($this->buscar) . '%';

                $query->where(function ($subQuery) use ($buscar) {
                    $subQuery
                        ->where('nombre', 'like', $buscar)
                        ->orWhere('codigo_interno', 'like', $buscar)
                        ->orWhereHas('marca', function ($marcaQuery) use ($buscar) {
                            $marcaQuery->where('nombre', 'like', $buscar);
                        });
                });
            }
        )
        ->when(
            $this->filtroTipo,
            function ($query) {
                $query->where('tipo_control', $this->filtroTipo);
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

    /*
    |--------------------------------------------------------------------------
    | Calcular préstamos pendientes de las herramientas de esta página
    |--------------------------------------------------------------------------
    */
    $idsHerramientas = $herramientas->getCollection()
        ->pluck('id')
        ->all();

    $pendientes = PrestamoDetalle::query()
        ->whereIn('herramienta_id', $idsHerramientas)
        ->where('estado', 'prestado')
        ->select('herramienta_id')
        ->selectRaw(
            'COALESCE(SUM(cantidad_prestada - cantidad_devuelta), 0) AS total_pendiente'
        )
        ->groupBy('herramienta_id')
        ->pluck('total_pendiente', 'herramienta_id');

    /*
    |--------------------------------------------------------------------------
    | Agregar cantidades calculadas a los resultados
    |--------------------------------------------------------------------------
    */
    foreach ($herramientas as $herramienta) {

        $cantidadPrestada = round(
            (float) ($pendientes[$herramienta->id] ?? 0),
            2
        );

        $cantidadDisponible = max(
            0,
            round(
                (float) $herramienta->cantidad_actual
                    - $cantidadPrestada,
                2
            )
        );

        $herramienta->setAttribute(
            'cantidad_prestada_pendiente',
            $cantidadPrestada
        );

        $herramienta->setAttribute(
            'cantidad_disponible_calculada',
            $cantidadDisponible
        );
    }

    $categorias = Categoria::query()
        ->where('activo', true)
        ->where('aplica_a', 'herramienta')
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
        'livewire.inventario.herramientas.index',
        compact(
            'herramientas',
            'categorias',
            'marcas',
            'unidades'
        )
    );
}

}
