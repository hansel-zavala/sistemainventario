<?php

namespace App\Livewire\Movimientos\Prestamos;

use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use Illuminate\Support\Carbon;
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
    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public string $departamentoDestinoId = '';
    public string $personaRecibe = '';
    public string $fechaPrevistaDevolucion = '';
    public string $observaciones = '';

    public array $activosSeleccionados = [];

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function nuevoPrestamo(): void
    {
        $this->resetFormulario();
        $this->resetValidation();

        $this->agregarActivo();
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
        $this->fechaPrevistaDevolucion = '';
        $this->observaciones = '';
        $this->activosSeleccionados = [];
    }

    public function agregarActivo(): void
    {
        $this->activosSeleccionados[] = [
            'tipo' => '',
            'item_id' => '',
        ];
    }

    public function eliminarActivo(int $indice): void
    {
        if (count($this->activosSeleccionados) <= 1) {
            return;
        }

        unset($this->activosSeleccionados[$indice]);

        $this->activosSeleccionados = array_values(
            $this->activosSeleccionados
        );

        $this->resetValidation();
    }

    public function updatedActivosSeleccionados(
        $valor,
        string $clave
    ): void {
        if (str_ends_with($clave, '.tipo')) {
            $indice = (int) explode('.', $clave)[0];

            if (isset($this->activosSeleccionados[$indice])) {
                $this->activosSeleccionados[$indice]['item_id'] = '';
            }
        }
    }

    protected function rules(): array
    {
        return [
            'departamentoDestinoId' => [
                'required',
                'integer',
                'exists:departamentos,id',
            ],

            'personaRecibe' => [
                'required',
                'string',
                'max:150',
            ],

            'fechaPrevistaDevolucion' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'activosSeleccionados' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],

            'activosSeleccionados.*.tipo' => [
                'required',
                'in:herramienta,equipo',
            ],

            'activosSeleccionados.*.item_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'departamentoDestinoId.required' =>
                'Debe seleccionar el departamento destino.',

            'departamentoDestinoId.exists' =>
                'El departamento seleccionado no es válido.',

            'personaRecibe.required' =>
                'Debe indicar la persona que recibe los activos.',

            'fechaPrevistaDevolucion.required' =>
                'Debe indicar fecha y hora de devolución.',

            'fechaPrevistaDevolucion.date_format' =>
                'Ingrese una fecha y hora válidas.',

            'activosSeleccionados.min' =>
                'Debe agregar al menos un activo.',

            'activosSeleccionados.max' =>
                'Puede agregar un máximo de 30 activos.',

            'activosSeleccionados.*.tipo.required' =>
                'Seleccione el tipo de activo.',

            'activosSeleccionados.*.tipo.in' =>
                'El tipo seleccionado no es válido.',

            'activosSeleccionados.*.item_id.required' =>
                'Seleccione un activo.',

            'activosSeleccionados.*.item_id.integer' =>
                'El activo seleccionado no es válido.',
        ];
    }

    public function guardarPrestamo(): void
    {
        $this->validate();

        $fechaDevolucion = Carbon::createFromFormat(
            'Y-m-d\TH:i',
            $this->fechaPrevistaDevolucion
        );

        if ($fechaDevolucion->lessThanOrEqualTo(now())) {
            $this->addError(
                'fechaPrevistaDevolucion',
                'La devolución debe programarse para una fecha y hora futuras.'
            );

            return;
        }

        // Evitar el mismo activo dos veces en el formulario.
        $claves = [];

        foreach ($this->activosSeleccionados as $indice => $activo) {
            $clave = $activo['tipo'] . ':' . $activo['item_id'];

            if (in_array($clave, $claves, true)) {
                $this->addError(
                    "activosSeleccionados.$indice.item_id",
                    'Este activo ya fue agregado al préstamo.'
                );
            }

            $claves[] = $clave;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        /*
         * Ordenamos los activos para adquirir bloqueos
         * siempre en el mismo orden.
         */
        $activosOrdenados = collect($this->activosSeleccionados)
            ->sortBy(fn ($activo) =>
                $activo['tipo'] . ':' .
                str_pad((string) $activo['item_id'], 20, '0', STR_PAD_LEFT)
            )
            ->values();

        DB::transaction(function () use (
            $fechaDevolucion,
            $activosOrdenados
        ) {
            $activosValidados = [];

            /*
             * Validamos dentro de la transacción.
             * Los bloqueos se mantienen hasta finalizar.
             */
            foreach ($activosOrdenados as $activo) {
                $tipo = $activo['tipo'];
                $id = (int) $activo['item_id'];

                if ($tipo === 'herramienta') {
                    $registro = Herramienta::query()
                        ->whereKey($id)
                        ->where('activo', true)
                        ->where('tipo_control', 'individual')
                        ->lockForUpdate()
                        ->first();
                } else {
                    $registro = Equipo::query()
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $registro) {
                    throw ValidationException::withMessages([
                        'activosSeleccionados' =>
                            'Uno de los activos ya no se encuentra disponible.',
                    ]);
                }

                $prestado = PrestamoDetalle::query()
                    ->where('estado', 'prestado')
                    ->where(function ($query) use ($tipo, $id) {
                        if ($tipo === 'herramienta') {
                            $query->where('herramienta_id', $id);
                        } else {
                            $query->where('equipo_id', $id);
                        }
                    })
                    ->exists();

                if ($prestado) {
                    throw ValidationException::withMessages([
                        'activosSeleccionados' =>
                            'Uno de los activos ya está prestado. Actualice el formulario.',
                    ]);
                }

                $activosValidados[] = [
                    'tipo' => $tipo,
                    'id' => $id,
                ];
            }

            // Crear cabecera del préstamo.
            $prestamo = Prestamo::create([
                'departamento_destino_id' =>
                    (int) $this->departamentoDestinoId,

                'persona_recibe' =>
                    trim($this->personaRecibe),

                'fecha_prestamo' =>
                    now(),

                'fecha_prevista_devolucion' =>
                    $fechaDevolucion,

                'estado' => 'activo',

                'observaciones' =>
                    trim($this->observaciones) !== ''
                        ? trim($this->observaciones)
                        : null,

                'registrado_por' =>
                    Auth::id(),
            ]);

            // Crear un detalle para cada activo prestado.
            foreach ($activosValidados as $activo) {
                PrestamoDetalle::create([
                    'prestamo_id' => $prestamo->id,

                    'herramienta_id' =>
                        $activo['tipo'] === 'herramienta'
                            ? $activo['id']
                            : null,

                    'equipo_id' =>
                        $activo['tipo'] === 'equipo'
                            ? $activo['id']
                            : null,

                    'estado' => 'prestado',
                ]);
            }
        }, 3);

        $this->cerrarModal();
        $this->resetPage();

        session()->flash(
            'mensaje',
            'Préstamo de activos registrado correctamente.'
        );
    }

    public function render()
    {
        $prestamos = Prestamo::query()
            ->with([
                'departamentoDestino',
                'usuario',
                'detalles.herramienta.marca',
                'detalles.equipo.marca',
                'detalles.equipo.tipoEquipo',
            ])
            ->when($this->buscar !== '', function ($query) {
                $buscar = '%' . trim($this->buscar) . '%';

                $query->where(function ($subQuery) use ($buscar) {
                    $subQuery
                        ->where('persona_recibe', 'like', $buscar)
                        ->orWhereHas(
                            'departamentoDestino',
                            fn ($q) => $q->where('nombre', 'like', $buscar)
                        )
                        ->orWhereHas(
                            'detalles.herramienta',
                            function ($q) use ($buscar) {
                                $q->where('nombre', 'like', $buscar)
                                    ->orWhere('codigo_interno', 'like', $buscar);
                            }
                        )
                        ->orWhereHas(
                            'detalles.equipo',
                            function ($q) use ($buscar) {
                                $q->where('numero_inventario', 'like', $buscar)
                                    ->orWhere('numero_serie', 'like', $buscar)
                                    ->orWhere('modelo', 'like', $buscar);
                            }
                        );
                });
            })
            ->when(
                $this->filtroEstado === 'activo',
                fn ($query) => $query->where('estado', 'activo')
            )
            ->when(
                $this->filtroEstado === 'completado',
                fn ($query) => $query->where('estado', 'completado')
            )
            ->when(
                $this->filtroEstado === 'vencido',
                fn ($query) => $query
                    ->where('estado', 'activo')
                    ->where('fecha_prevista_devolucion', '<', now())
            )
            ->latest('fecha_prestamo')
            ->paginate(10);

        $departamentos = Departamento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $herramientas = Herramienta::query()
            ->where('activo', true)
            ->where('tipo_control', 'individual')
            ->whereDoesntHave(
                'prestamos',
                fn ($query) => $query->where('estado', 'prestado')
            )
            ->with('marca')
            ->orderBy('nombre')
            ->get();

        $equipos = Equipo::query()
            ->whereDoesntHave(
                'prestamos',
                fn ($query) => $query->where('estado', 'prestado')
            )
            ->with(['marca', 'tipoEquipo'])
            ->orderBy('numero_inventario')
            ->get();

        return view(
            'livewire.movimientos.prestamos.index',
            compact(
                'prestamos',
                'departamentos',
                'herramientas',
                'equipos'
            )
        );
    }
}
