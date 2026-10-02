<?php

namespace App\Livewire\Movimientos\Entradas;

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

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public bool $mostrarModal = false;

    public string $observaciones = '';

    public array $detalles = [];


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function nuevaEntrada(): void
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
        /*
        | Cuando cambia el tipo de artículo,
        | limpiamos el artículo seleccionado.
        */

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
        ];
    }


    public function guardarEntrada(): void
    {
        $this->validate();

        $articulosUsados = [];

        foreach ($this->detalles as $indice => $detalle) {

            $claveArticulo =
                $detalle['tipo_item']
                . '-'
                . $detalle['item_id'];

            if (in_array($claveArticulo, $articulosUsados, true)) {

                $this->addError(
                    "detalles.$indice.item_id",
                    'Este artículo ya fue agregado en esta entrada.'
                );

                continue;
            }

            $articulosUsados[] = $claveArticulo;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validaciones de cada artículo
        |--------------------------------------------------------------------------
        */

        foreach ($this->detalles as $indice => $detalle) {

            $item = $this->buscarItem(
                $detalle['tipo_item'],
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
            | Herramienta individual no aumenta cantidad.
            | Cada individual debe registrarse como herramienta separada.
            */

            if (
                $detalle['tipo_item'] === 'herramienta'
                && $item->tipo_control === 'individual'
            ) {

                $this->addError(
                    "detalles.$indice.item_id",
                    'Las herramientas individuales no reciben entradas por cantidad.'
                );
            }


            /*
            | Validar decimales para herramientas e insumos.
            */

            if (
                in_array(
                    $detalle['tipo_item'],
                    ['herramienta', 'insumo'],
                    true
                )
            ) {

                $cantidad =
                    (float) $detalle['cantidad'];

                if (
                    ! $item->unidadMedida->permite_decimales
                    && fmod($cantidad, 1.0) !== 0.0
                ) {

                    $this->addError(
                        "detalles.$indice.cantidad",
                        'La unidad de medida de este artículo no permite cantidades decimales.'
                    );
                }
            }


            /*
            | Las entradas de tinta representan botellas completas.
            */

            if (
                $detalle['tipo_item'] === 'tinta'
                && fmod(
                    (float) $detalle['cantidad'],
                    1.0
                ) !== 0.0
            ) {

                $this->addError(
                    "detalles.$indice.cantidad",
                    'Las entradas de tinta deben registrarse en botellas completas.'
                );
            }
        }


        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Guardar movimiento completo
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () {

            $movimiento = MovimientoInventario::create([
                'tipo' => 'entrada',

                'fecha_movimiento' =>
                    now(),

                'observaciones' =>
                    trim($this->observaciones) !== ''
                        ? trim($this->observaciones)
                        : null,

                'registrado_por' =>
                    Auth::id(),
            ]);


            foreach ($this->detalles as $detalle) {

                $tipo =
                    $detalle['tipo_item'];

                $cantidad =
                    (float) $detalle['cantidad'];


                $item = $this->buscarItemParaActualizar(
                    $tipo,
                    (int) $detalle['item_id']
                );


                if (! $item) {
                    throw ValidationException::withMessages([
                        'detalles' =>
                            'Uno de los artículos ya no se encuentra disponible.',
                    ]);
                }


                $existenciaAnterior =
                    $this->obtenerExistencia(
                        $tipo,
                        $item
                    );


                $this->aplicarEntrada(
                    $tipo,
                    $item,
                    $cantidad
                );


                $item->refresh();


                $existenciaPosterior =
                    $this->obtenerExistencia(
                        $tipo,
                        $item
                    );


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
            'Entrada de inventario registrada correctamente.'
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

            default =>
                null,
        };


        if (! $query) {
            return null;
        }


        return $query
            ->whereKey($id)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();
    }


    private function obtenerExistencia(
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


    private function aplicarEntrada(
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
                    + $cantidad,
            ]);

            return;
        }


        if ($tipo === 'tinta') {

            $item->update([
                'botellas_completas' =>
                    (int) $item->botellas_completas
                    + (int) $cantidad,
            ]);
        }
    }


    public function render()
    {
        $movimientos = MovimientoInventario::query()
            ->where('tipo', 'entrada')

            ->with([
                'usuario',
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


        $herramientas = Herramienta::query()
            ->where('activo', true)
            ->where(
                'tipo_control',
                'cantidad'
            )
            ->with('unidadMedida')
            ->orderBy('nombre')
            ->get();


        $insumos = Insumo::query()
            ->where('activo', true)
            ->with('unidadMedida')
            ->orderBy('nombre')
            ->get();


        $tintas = Tinta::query()
            ->where('activo', true)
            ->with('marca')
            ->orderBy('nombre')
            ->orderBy('color')
            ->get();


        return view(
            'livewire.movimientos.entradas.index',
            compact(
                'movimientos',
                'herramientas',
                'insumos',
                'tintas'
            )
        );
    }
}