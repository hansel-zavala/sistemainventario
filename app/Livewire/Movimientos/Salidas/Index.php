<?php

namespace App\Livewire\Movimientos\Salidas;

use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use App\Models\Tinta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Departamento;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $mostrarModal = false;

    public string $observaciones = '';

    public string $departamentoDestinoId = '';

    public string $personaRecibe = '';

    public string $motivo = '';

    public array $detalles = [];


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function nuevaSalida(): void
    {
        $this->resetFormulario();

        $this->agregarDetalle();

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
        $this->departamentoDestinoId = '';
        $this->personaRecibe = '';
        $this->motivo = '';
        $this->observaciones = '';
        $this->detalles = [];
    }


    public function agregarDetalle(): void
    {
        $this->detalles[] = [
            'tipo_item' => '',
            'item_id' => '',
            'cantidad' => '',
        ];
    }


    public function eliminarDetalle(int $indice): void
    {
        if (count($this->detalles) <= 1) {
            return;
        }

        unset($this->detalles[$indice]);

        $this->detalles = array_values(
            $this->detalles
        );

        $this->resetValidation();
    }


    public function updatedDetalles(
        $value,
        string $key
    ): void {

        if (
            str_ends_with(
                $key,
                '.tipo_item'
            )
        ) {
            $partes = explode('.', $key);

            $indice = (int) $partes[0];

            if (isset($this->detalles[$indice])) {
                $this->detalles[$indice]['item_id'] = '';
            }
        }
    }


    protected function rules(): array
    {
        return [
            'departamentoDestinoId' => [
                'required',
                'exists:departamentos,id',
            ],

            'personaRecibe' => [
                'required',
                'string',
                'max:150',
            ],

            'motivo' => [
                'required',
                'string',
                'max:255',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],

            'detalles.*.tipo_item' => [
                'required',
                'in:herramienta,insumo,tinta',
            ],

            'detalles.*.item_id' => [
                'required',
                'integer',
            ],

            'detalles.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'observaciones.max' =>
                'Las observaciones no pueden superar los 2000 caracteres.',

            'detalles.*.tipo_item.required' =>
                'Debe seleccionar el tipo de artículo.',

            'detalles.*.tipo_item.in' =>
                'El tipo de artículo seleccionado no es válido.',

            'detalles.*.item_id.required' =>
                'Debe seleccionar un artículo.',

            'detalles.*.item_id.integer' =>
                'El artículo seleccionado no es válido.',

            'detalles.*.cantidad.required' =>
                'Debe ingresar la cantidad.',

            'detalles.*.cantidad.numeric' =>
                'La cantidad debe ser numérica.',

            'detalles.*.cantidad.gt' =>
                'La cantidad debe ser mayor que cero.',

            'departamentoDestinoId.required' =>
                'Debe seleccionar el departamento destino.',

            'departamentoDestinoId.exists' =>
                'El departamento seleccionado no es válido.',

            'personaRecibe.required' =>
                'Debe indicar quién recibe los artículos.',

            'personaRecibe.max' =>
                'El nombre de la persona no puede superar los 150 caracteres.',

            'motivo.required' =>
                'Debe indicar el motivo de la salida.',

            'motivo.max' =>
                'El motivo no puede superar los 255 caracteres.',
        ];
    }


    public function guardarSalida(): void
    {
        $this->validate();


        /*
        |--------------------------------------------------------------------------
        | Evitar artículos duplicados
        |--------------------------------------------------------------------------
        */

        $articulosUsados = [];

        foreach ($this->detalles as $indice => $detalle) {

            $claveArticulo =
                $detalle['tipo_item']
                . '-'
                . $detalle['item_id'];


            if (
                in_array(
                    $claveArticulo,
                    $articulosUsados,
                    true
                )
            ) {

                $this->addError(
                    "detalles.$indice.item_id",
                    'Este artículo ya fue agregado en esta salida.'
                );

                continue;
            }


            $articulosUsados[] =
                $claveArticulo;
        }


        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Validaciones antes de guardar
        |--------------------------------------------------------------------------
        */

        foreach ($this->detalles as $indice => $detalle) {

            $tipo =
                $detalle['tipo_item'];

            $cantidad =
                (float) $detalle['cantidad'];


            $item = $this->buscarItem(
                $tipo,
                (int) $detalle['item_id']
            );


            if (! $item) {

                $this->addError(
                    "detalles.$indice.item_id",
                    'El artículo seleccionado no existe o está inactivo.'
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | No utilizar herramientas individuales
            |--------------------------------------------------------------------------
            */

            if (
                $tipo === 'herramienta'
                && $item->tipo_control === 'individual'
            ) {

                $this->addError(
                    "detalles.$indice.item_id",
                    'Las herramientas individuales deben manejarse mediante préstamos.'
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Validar decimales
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $tipo,
                    [
                        'herramienta',
                        'insumo',
                    ],
                    true
                )
            ) {

                if (
                    ! $item->unidadMedida->permite_decimales
                    && fmod($cantidad, 1.0) !== 0.0
                ) {

                    $this->addError(
                        "detalles.$indice.cantidad",
                        'La unidad de medida de este artículo no permite cantidades decimales.'
                    );

                    continue;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Tinta: solamente botellas completas
            |--------------------------------------------------------------------------
            */

            if (
                $tipo === 'tinta'
                && fmod($cantidad, 1.0) !== 0.0
            ) {

                $this->addError(
                    "detalles.$indice.cantidad",
                    'Las salidas de tinta deben registrarse en botellas completas.'
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Validar existencia
            |--------------------------------------------------------------------------
            */

            $existenciaDisponible =
                $this->obtenerExistenciaDisponibleParaSalida(
                    $tipo,
                    $item
                );


            if ($cantidad > $existenciaDisponible) {

                $this->addError(
                    "detalles.$indice.cantidad",
                    'La cantidad solicitada supera la existencia disponible.'
                );
            }
        }


        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Guardar salida
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () {

            $movimiento = MovimientoInventario::create([
                'tipo' => 'salida',

                'fecha_movimiento' =>
                    now(),

                'departamento_destino_id' =>
                    (int) $this->departamentoDestinoId,

                'persona_recibe' =>
                    trim($this->personaRecibe),

                'motivo' =>
                    trim($this->motivo),

                'observaciones' =>
                    trim($this->observaciones) !== ''
                        ? trim($this->observaciones)
                        : null,

                'registrado_por' =>
                    Auth::id(),
            ]);


            foreach ($this->detalles as $indice => $detalle) {

                $tipo =
                    $detalle['tipo_item'];

                $cantidad =
                    (float) $detalle['cantidad'];


                /*
                |--------------------------------------------------------------------------
                | Bloquear artículo mientras se modifica
                |--------------------------------------------------------------------------
                */

                $item =
                    $this->buscarItemParaActualizar(
                        $tipo,
                        (int) $detalle['item_id']
                    );


                if (! $item) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.item_id" =>
                            'El artículo ya no se encuentra disponible.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Volver a validar existencia dentro de la transacción
                |--------------------------------------------------------------------------
                */

                $disponible =
                    $this->obtenerExistenciaDisponibleParaSalida(
                        $tipo,
                        $item
                    );


                if ($cantidad > $disponible) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.cantidad" =>
                            'La existencia cambió y ya no hay suficiente cantidad disponible.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Existencia antes
                |--------------------------------------------------------------------------
                */

                $existenciaAnterior =
                    $this->obtenerExistenciaTotal(
                        $tipo,
                        $item
                    );


                /*
                |--------------------------------------------------------------------------
                | Aplicar salida
                |--------------------------------------------------------------------------
                */

                $this->aplicarSalida(
                    $tipo,
                    $item,
                    $cantidad
                );


                $item->refresh();


                /*
                |--------------------------------------------------------------------------
                | Existencia después
                |--------------------------------------------------------------------------
                */

                $existenciaPosterior =
                    $this->obtenerExistenciaTotal(
                        $tipo,
                        $item
                    );


                /*
                |--------------------------------------------------------------------------
                | Guardar detalle histórico
                |--------------------------------------------------------------------------
                */

                MovimientoInventarioDetalle::create([
                    'movimiento_inventario_id' =>
                        $movimiento->id,

                    'tipo_item' =>
                        $tipo,

                    'item_id' =>
                        $item->id,

                    'cantidad' =>
                        $cantidad,

                    'existencia_anterior' =>
                        $existenciaAnterior,

                    'existencia_posterior' =>
                        $existenciaPosterior,
                ]);
            }
        });


        session()->flash(
            'mensaje',
            'Salida de inventario registrada correctamente.'
        );


        $this->cerrarModal();

        $this->resetPage();
    }


    private function buscarItem(
        string $tipo,
        int $id
    ) {
        return match ($tipo) {

            'herramienta' =>
                Herramienta::query()
                    ->whereKey($id)
                    ->where('activo', true)
                    ->with('unidadMedida')
                    ->first(),

            'insumo' =>
                Insumo::query()
                    ->whereKey($id)
                    ->where('activo', true)
                    ->with('unidadMedida')
                    ->first(),

            'tinta' =>
                Tinta::query()
                    ->whereKey($id)
                    ->where('activo', true)
                    ->first(),

            default => null,
        };
    }


    private function buscarItemParaActualizar(
        string $tipo,
        int $id
    ) {
        $query = match ($tipo) {

            'herramienta' =>
                Herramienta::query(),

            'insumo' =>
                Insumo::query(),

            'tinta' =>
                Tinta::query(),

            default => null,
        };


        if (! $query) {
            return null;
        }


        $item = $query
            ->whereKey($id)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();


        if (
            $item
            && in_array(
                $tipo,
                [
                    'herramienta',
                    'insumo',
                ],
                true
            )
        ) {
            $item->load('unidadMedida');
        }


        return $item;
    }


    /*
    |--------------------------------------------------------------------------
    | Existencia disponible para sacar
    |--------------------------------------------------------------------------
    |
    | En Tinta solamente cuentan botellas completas.
    |
    | Si tenemos:
    |
    | 1 botella completa + 80 % abierta
    |
    | para una salida física tenemos:
    |
    | 1 botella completa disponible
    |
    | El 80 % abierto queda reservado para recargas.
    |
    */

    private function obtenerExistenciaDisponibleParaSalida(
        string $tipo,
        $item
    ): float {

        return match ($tipo) {

            'herramienta',
            'insumo' =>
                (float) $item->cantidad_actual,

            'tinta' =>
                (float) $item->botellas_completas,

            default => 0,
        };
    }


    /*
    |--------------------------------------------------------------------------
    | Existencia total equivalente
    |--------------------------------------------------------------------------
    |
    | Esta es la que guardamos en el historial.
    |
    */

    private function obtenerExistenciaTotal(
        string $tipo,
        $item
    ): float {

        return match ($tipo) {

            'herramienta',
            'insumo' =>
                (float) $item->cantidad_actual,

            'tinta' =>
                (float) $item->botellas_completas
                + (
                    (float) $item->porcentaje_botella_abierta
                    / 100
                ),

            default => 0,
        };
    }


    private function aplicarSalida(
        string $tipo,
        $item,
        float $cantidad
    ): void {

        if (
            $tipo === 'herramienta'
            || $tipo === 'insumo'
        ) {

            $item->update([
                'cantidad_actual' =>
                    (float) $item->cantidad_actual
                    - $cantidad,
            ]);

            return;
        }


        if ($tipo === 'tinta') {

            $item->update([
                'botellas_completas' =>
                    (int) $item->botellas_completas
                    - (int) $cantidad,
            ]);
        }
    }


    public function render()
    {
        /*
        |--------------------------------------------------------------------------
        | Historial de salidas
        |--------------------------------------------------------------------------
        */

        $movimientos = MovimientoInventario::query()
            ->where('tipo', 'salida')

            ->with([
                'usuario',
                'departamentoDestino',
                'detalles',
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
                                    'observaciones',
                                    'like',
                                    $buscar
                                )

                                ->orWhereHas(
                                    'usuario',
                                    function ($usuarioQuery) use ($buscar) {

                                        $usuarioQuery->where(
                                            'name',
                                            'like',
                                            $buscar
                                        );
                                    }
                                );
                        }
                    );
                }
            )

            ->latest('fecha_movimiento')
            ->paginate(10);


        /*
        |--------------------------------------------------------------------------
        | Herramientas por cantidad
        |--------------------------------------------------------------------------
        */

        $herramientas = Herramienta::query()
            ->where('activo', true)

            ->where(
                'tipo_control',
                'cantidad'
            )

            ->where(
                'cantidad_actual',
                '>',
                0
            )

            ->with('unidadMedida')

            ->orderBy('nombre')

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Insumos con existencia
        |--------------------------------------------------------------------------
        */

        $insumos = Insumo::query()
            ->where('activo', true)

            ->where(
                'cantidad_actual',
                '>',
                0
            )

            ->with('unidadMedida')

            ->orderBy('nombre')

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Tinta con botellas completas
        |--------------------------------------------------------------------------
        */

        $tintas = Tinta::query()
            ->where('activo', true)

            ->where(
                'botellas_completas',
                '>',
                0
            )

            ->with('marca')

            ->orderBy('nombre')
            ->orderBy('color')

            ->get();


        $departamentos = Departamento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        return view(
            'livewire.movimientos.salidas.index',
            compact(
                'movimientos',
                'herramientas',
                'insumos',
                'tintas',
                'departamentos'
            )
        );
    }
}