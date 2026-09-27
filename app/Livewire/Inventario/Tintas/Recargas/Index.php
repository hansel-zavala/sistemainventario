<?php

namespace App\Livewire\Inventario\Tintas\Recargas;

use App\Models\Equipo;
use App\Models\RecargaTinta;
use App\Models\RecargaTintaDetalle;
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

    public string $equipoId = '';

    public string $observaciones = '';

    public array $detalles = [];


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function nuevaRecarga(): void
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
        $this->equipoId = '';
        $this->observaciones = '';
        $this->detalles = [];
    }


    public function agregarDetalle(): void
    {
        $this->detalles[] = [
            'tinta_id' => '',
            'porcentaje_antes' => '0',
            'porcentaje_agregado' => '0',
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


    protected function rules(): array
    {
        return [
            'equipoId' => [
                'required',
                'exists:equipos,id',
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

            'detalles.*.tinta_id' => [
                'required',
                'distinct',
                'exists:tintas,id',
            ],

            'detalles.*.porcentaje_antes' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'detalles.*.porcentaje_agregado' => [
                'required',
                'numeric',
                'gt:0',
                'max:100',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'equipoId.required' =>
                'Debe seleccionar una impresora.',

            'equipoId.exists' =>
                'La impresora seleccionada no es válida.',

            'observaciones.max' =>
                'Las observaciones no pueden superar los 2000 caracteres.',

            'detalles.required' =>
                'Debe registrar al menos un color de tinta.',

            'detalles.min' =>
                'Debe registrar al menos un color de tinta.',

            'detalles.*.tinta_id.required' =>
                'Debe seleccionar una tinta.',

            'detalles.*.tinta_id.distinct' =>
                'No puede seleccionar la misma tinta más de una vez.',

            'detalles.*.tinta_id.exists' =>
                'La tinta seleccionada no es válida.',

            'detalles.*.porcentaje_antes.required' =>
                'Debe indicar el nivel antes de la recarga.',

            'detalles.*.porcentaje_antes.numeric' =>
                'El nivel anterior debe ser un número.',

            'detalles.*.porcentaje_antes.min' =>
                'El nivel anterior no puede ser negativo.',

            'detalles.*.porcentaje_antes.max' =>
                'El nivel anterior no puede superar 100 %.',

            'detalles.*.porcentaje_agregado.required' =>
                'Debe indicar cuánto porcentaje se agregó.',

            'detalles.*.porcentaje_agregado.numeric' =>
                'El porcentaje agregado debe ser un número.',

            'detalles.*.porcentaje_agregado.gt' =>
                'El porcentaje agregado debe ser mayor que cero.',

            'detalles.*.porcentaje_agregado.max' =>
                'El porcentaje agregado no puede superar 100 %.',
        ];
    }


    public function guardarRecarga(): void
    {
        $this->validate();


        /*
        |--------------------------------------------------------------------------
        | Validar que realmente sea una impresora
        |--------------------------------------------------------------------------
        */

        $esImpresora = Equipo::query()
            ->whereKey((int) $this->equipoId)
            ->whereHas(
                'tipoEquipo',
                function ($query) {
                    $query->where(
                        'nombre',
                        'like',
                        '%impresora%'
                    );
                }
            )
            ->exists();


        if (! $esImpresora) {
            $this->addError(
                'equipoId',
                'El equipo seleccionado no corresponde a una impresora.'
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Validar niveles finales antes de tocar inventario
        |--------------------------------------------------------------------------
        */

        foreach ($this->detalles as $indice => $detalle) {

            $antes =
                (float) $detalle['porcentaje_antes'];

            $agregado =
                (float) $detalle['porcentaje_agregado'];

            $despues =
                $antes + $agregado;


            if ($despues > 100) {

                $this->addError(
                    "detalles.$indice.porcentaje_agregado",
                    'El nivel final no puede superar 100 %.'
                );
            }
        }


        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Guardar operación completa
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () {

            $recarga = RecargaTinta::create([
                'equipo_id' =>
                    (int) $this->equipoId,

                'observaciones' =>
                    trim($this->observaciones) !== ''
                        ? trim($this->observaciones)
                        : null,

                'realizado_por' =>
                    Auth::id(),
            ]);


            foreach ($this->detalles as $indice => $detalle) {

                /*
                |--------------------------------------------------------------------------
                | Bloquear la tinta durante el cálculo
                |--------------------------------------------------------------------------
                |
                | Esto evita que dos usuarios descuenten la misma existencia
                | simultáneamente y provoquen un valor incorrecto.
                |
                */

                $tinta = Tinta::query()
                    ->whereKey(
                        (int) $detalle['tinta_id']
                    )
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();


                if (! $tinta) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.tinta_id" =>
                            'La tinta seleccionada no está disponible.',
                    ]);
                }


                $antes =
                    (float) $detalle['porcentaje_antes'];

                $agregado =
                    (float) $detalle['porcentaje_agregado'];

                $despues =
                    $antes + $agregado;


                /*
                |--------------------------------------------------------------------------
                | Calcular existencia real disponible
                |--------------------------------------------------------------------------
                */

                $existenciaDisponible =
                    ($tinta->botellas_completas * 100)
                    + (float) $tinta->porcentaje_botella_abierta;


                if ($agregado > $existenciaDisponible) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.porcentaje_agregado" =>
                            'No hay suficiente tinta disponible para realizar esta recarga.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Crear detalle histórico
                |--------------------------------------------------------------------------
                */

                RecargaTintaDetalle::create([
                    'recarga_tinta_id' =>
                        $recarga->id,

                    'tinta_id' =>
                        $tinta->id,

                    'porcentaje_antes' =>
                        $antes,

                    'porcentaje_agregado' =>
                        $agregado,

                    'porcentaje_despues' =>
                        $despues,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Descontar inventario
                |--------------------------------------------------------------------------
                */

                $this->descontarTinta(
                    $tinta,
                    $agregado
                );
            }
        });


        session()->flash(
            'mensaje',
            'Recarga de tinta registrada correctamente.'
        );


        $this->cerrarModal();

        $this->resetPage();
    }


    private function descontarTinta(
        Tinta $tinta,
        float $porcentajeConsumido
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Convertir todo el inventario a porcentaje equivalente
        |--------------------------------------------------------------------------
        |
        | Ejemplo:
        |
        | 2 botellas completas + 40 % abierta
        | = 240 %
        |
        */

        $existenciaTotal =
            ($tinta->botellas_completas * 100)
            + (float) $tinta->porcentaje_botella_abierta;


        $existenciaRestante = round(
            $existenciaTotal - $porcentajeConsumido,
            2
        );


        /*
        |--------------------------------------------------------------------------
        | Volver a separar en botellas + botella abierta
        |--------------------------------------------------------------------------
        */

        $botellasCompletas =
            (int) floor(
                $existenciaRestante / 100
            );


        $porcentajeAbierto = round(
            $existenciaRestante
            - ($botellasCompletas * 100),
            2
        );


        $tinta->update([
            'botellas_completas' =>
                $botellasCompletas,

            'porcentaje_botella_abierta' =>
                $porcentajeAbierto,
        ]);
    }


    public function render()
    {
        /*
        |--------------------------------------------------------------------------
        | Historial de recargas
        |--------------------------------------------------------------------------
        */

        $recargas = RecargaTinta::query()
            ->with([
                'equipo.tipoEquipo',
                'equipo.marca',
                'usuario',
                'detalles.tinta.marca',
            ])

            ->when(
                $this->buscar,
                function ($query) {

                    $buscar =
                        '%' . trim($this->buscar) . '%';


                    $query->where(
                        function ($subQuery) use ($buscar) {

                            $subQuery

                                ->whereHas(
                                    'equipo',
                                    function ($equipoQuery) use ($buscar) {

                                        $equipoQuery
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
                                            );
                                    }
                                )


                                ->orWhereHas(
                                    'detalles.tinta',
                                    function ($tintaQuery) use ($buscar) {

                                        $tintaQuery
                                            ->where(
                                                'nombre',
                                                'like',
                                                $buscar
                                            )

                                            ->orWhere(
                                                'color',
                                                'like',
                                                $buscar
                                            );
                                    }
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

            ->latest()
            ->paginate(10);


        /*
        |--------------------------------------------------------------------------
        | Solamente impresoras
        |--------------------------------------------------------------------------
        */

        $equipos = Equipo::query()
            ->whereHas(
                'tipoEquipo',
                function ($query) {

                    $query->where(
                        'nombre',
                        'like',
                        '%impresora%'
                    );
                }
            )

            ->with([
                'tipoEquipo',
                'marca',
                'departamento',
            ])

            ->orderBy('numero_inventario')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Solamente tintas activas con existencia
        |--------------------------------------------------------------------------
        */

        $tintas = Tinta::query()
            ->where('activo', true)

            ->where(
                function ($query) {

                    $query
                        ->where(
                            'botellas_completas',
                            '>',
                            0
                        )

                        ->orWhere(
                            'porcentaje_botella_abierta',
                            '>',
                            0
                        );
                }
            )

            ->with('marca')

            ->orderBy('nombre')
            ->orderBy('color')
            ->get();


        return view(
            'livewire.inventario.tintas.recargas.index',
            compact(
                'recargas',
                'equipos',
                'tintas'
            )
        );
    }
}