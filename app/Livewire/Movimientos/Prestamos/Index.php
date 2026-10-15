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
        $this->resetFormulario();
        $this->resetValidation();
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
            'cantidad' => '1',
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
                $this->activosSeleccionados[$indice]['cantidad'] = '1';
            }
        }
    }

    protected function rules(): array
    {
        return [
            'departamentoDestinoId' => [
                'required', 'integer', 'exists:departamentos,id',
            ],
            'personaRecibe' => [
                'required', 'string', 'max:150',
            ],
            'fechaPrevistaDevolucion' => [
                'required', 'date_format:Y-m-d\TH:i',
            ],
            'observaciones' => [
                'nullable', 'string', 'max:2000',
            ],
            'activosSeleccionados' => [
                'required', 'array', 'min:1', 'max:30',
            ],
            'activosSeleccionados.*.tipo' => [
                'required',
                'in:herramienta_individual,herramienta_cantidad,equipo',
            ],
            'activosSeleccionados.*.item_id' => [
                'required', 'integer', 'min:1',
            ],
            'activosSeleccionados.*.cantidad' => [
                'required', 'numeric', 'gt:0',
                'decimal:0,2',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'departamentoDestinoId.required' => 'Seleccione el departamento destino.',
            'departamentoDestinoId.exists' => 'El departamento seleccionado no es válido.',
            'personaRecibe.required' => 'Ingrese la persona responsable del préstamo.',
            'fechaPrevistaDevolucion.required' => 'Indique la fecha y hora prevista de devolución.',
            'fechaPrevistaDevolucion.date_format' => 'La fecha y hora no tienen un formato válido.',
            'activosSeleccionados.*.tipo.required' => 'Seleccione el tipo de activo.',
            'activosSeleccionados.*.item_id.required' => 'Seleccione el activo.',
            'activosSeleccionados.*.cantidad.required' => 'Ingrese la cantidad.',
            'activosSeleccionados.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'activosSeleccionados.*.cantidad.decimal' => 'La cantidad admite hasta dos decimales.',
        ];
    }

    private function campoErrorActivo(
        string $tipo,
        int $id
    ): string {
        foreach ($this->activosSeleccionados as $indice => $activo) {
            if (
                $activo['tipo'] === $tipo
                && (int) $activo['item_id'] === $id
            ) {
                return "activosSeleccionados.$indice.cantidad";
            }
        }

        return 'activosSeleccionados';
    }

    private function pendienteHerramienta(int $id): float
    {
        return round(
            (float) PrestamoDetalle::query()
                ->where('herramienta_id', $id)
                ->where('estado', 'prestado')
                ->selectRaw(
                    'COALESCE(SUM(cantidad_prestada - cantidad_devuelta), 0) AS pendiente'
                )
                ->value('pendiente'),
            2
        );
    }

    public function guardarPrestamo(): void
    {
        $this->validate();

        $fecha = Carbon::createFromFormat(
            '!Y-m-d\TH:i',
            $this->fechaPrevistaDevolucion
        );

        if ($fecha->lessThanOrEqualTo(now())) {
            $this->addError(
                'fechaPrevistaDevolucion',
                'La fecha y hora deben ser posteriores al momento actual.'
            );

            return;
        }

        // Impedir repetir un activo en una operación.
        $vistos = [];

        foreach ($this->activosSeleccionados as $indice => $activo) {
            $clave = $activo['tipo'].':'.$activo['item_id'];

            if (isset($vistos[$clave])) {
                $this->addError(
                    "activosSeleccionados.$indice.item_id",
                    'Este activo ya está incluido en el préstamo.'
                );
            }

            $vistos[$clave] = true;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Orden uniforme de bloqueo para reducir interbloqueos.
        $ordenados = collect($this->activosSeleccionados)
            ->sortBy(function ($activo) {
                $grupo = $activo['tipo'] === 'equipo'
                    ? '1'
                    : '0';

                return $grupo.':'.
                    str_pad(
                        (string) $activo['item_id'],
                        20,
                        '0',
                        STR_PAD_LEFT
                    );
            })
            ->values();

        DB::transaction(function () use ($fecha, $ordenados) {
            $detallesValidados = [];

            $departamento = Departamento::query()
                ->whereKey((int) $this->departamentoDestinoId)
                ->where('activo', true)
                ->first();

            if (! $departamento) {
                throw ValidationException::withMessages([
                    'departamentoDestinoId' => 'Seleccione un departamento activo.',
                ]);
            }

            foreach ($ordenados as $activo) {
                $tipo = $activo['tipo'];
                $id = (int) $activo['item_id'];
                $cantidad = round((float) $activo['cantidad'], 2);
                $campoError = $this->campoErrorActivo($tipo, $id);

                if ($tipo === 'equipo') {
                    $equipo = Equipo::query()
                        ->whereKey($id)
                        ->lockForUpdate()
                        ->first();

                    if (! $equipo) {
                        throw ValidationException::withMessages([
                            $campoError => 'El equipo no está disponible.',
                        ]);
                    }

                    if (abs($cantidad - 1) > 0.00001) {
                        throw ValidationException::withMessages([
                            $campoError => 'Cada equipo se presta individualmente (cantidad 1).',
                        ]);
                    }

                    $ocupado = PrestamoDetalle::query()
                        ->where('equipo_id', $id)
                        ->where('estado', 'prestado')
                        ->exists();

                    if ($ocupado) {
                        throw ValidationException::withMessages([
                            $campoError => 'El equipo ya está prestado.',
                        ]);
                    }

                    $detallesValidados[] = [
                        'herramienta_id' => null,
                        'equipo_id' => $id,
                        'cantidad_prestada' => 1,
                        'cantidad_devuelta' => 0,
                        'estado' => 'prestado',
                    ];

                    continue;
                }

                $controlEsperado = $tipo === 'herramienta_individual'
                    ? 'individual'
                    : 'cantidad';

                $herramienta = Herramienta::query()
                    ->whereKey($id)
                    ->where('activo', true)
                    ->where('tipo_control', $controlEsperado)
                    ->with('unidadMedida')
                    ->lockForUpdate()
                    ->first();

                if (! $herramienta) {
                    throw ValidationException::withMessages([
                        $campoError => 'La herramienta no está disponible o no corresponde al tipo seleccionado.',
                    ]);
                }

                if ($tipo === 'herramienta_individual') {
                    if (abs($cantidad - 1) > 0.00001) {
                        throw ValidationException::withMessages([
                            $campoError => 'Las herramientas individuales se prestan de una en una.',
                        ]);
                    }

                    $pendiente = $this->pendienteHerramienta($id);

                    if ($pendiente > 0) {
                        throw ValidationException::withMessages([
                            $campoError => 'Esta herramienta individual ya está prestada.',
                        ]);
                    }

                    if ((float) $herramienta->cantidad_actual < 1) {
                        throw ValidationException::withMessages([
                            $campoError => 'La herramienta no tiene existencia disponible.',
                        ]);
                    }
                } else {
                    $permiteDecimales = (bool)
                        $herramienta->unidadMedida?->permite_decimales;

                    if (
                        ! $permiteDecimales
                        && abs($cantidad - round($cantidad)) > 0.00001
                    ) {
                        throw ValidationException::withMessages([
                            $campoError => 'La unidad de medida no permite cantidades decimales.',
                        ]);
                    }

                    $pendiente = $this->pendienteHerramienta($id);

                    $disponible = round(
                        (float) $herramienta->cantidad_actual
                            - $pendiente,
                        2
                    );

                    if ($cantidad > $disponible) {
                        throw ValidationException::withMessages([
                            $campoError => 'Existencia insuficiente. Disponible: '.
                                max(0, $disponible),
                        ]);
                    }
                }

                $detallesValidados[] = [
                    'herramienta_id' => $id,
                    'equipo_id' => null,
                    'cantidad_prestada' => $cantidad,
                    'cantidad_devuelta' => 0,
                    'estado' => 'prestado',
                ];
            }

            $prestamo = Prestamo::create([
                'departamento_destino_id' => (int) $this->departamentoDestinoId,
                'persona_recibe' => trim($this->personaRecibe),
                'fecha_prestamo' => now(),
                'fecha_prevista_devolucion' => $fecha,
                'estado' => 'activo',
                'observaciones' => trim($this->observaciones) !== ''
                        ? trim($this->observaciones)
                        : null,
                'registrado_por' => Auth::id(),
            ]);

            foreach ($detallesValidados as $detalle) {
                $prestamo->detalles()->create($detalle);
            }
        }, 3);

        $this->cerrarModal();
        $this->resetPage();

        session()->flash(
            'mensaje',
            'Préstamo registrado correctamente.'
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
                $buscar = '%'.trim($this->buscar).'%';

                $query->where(function ($q) use ($buscar) {
                    $q->where('persona_recibe', 'like', $buscar)
                        ->orWhereHas(
                            'departamentoDestino',
                            fn ($d) => $d->where('nombre', 'like', $buscar)
                        )
                        ->orWhereHas(
                            'detalles.herramienta',
                            fn ($h) => $h
                                ->where('nombre', 'like', $buscar)
                                ->orWhere('codigo_interno', 'like', $buscar)
                        )
                        ->orWhereHas(
                            'detalles.equipo',
                            fn ($e) => $e
                                ->where('numero_inventario', 'like', $buscar)
                                ->orWhere('modelo', 'like', $buscar)
                        );
                });
            })
            ->when(
                $this->filtroEstado === 'activo',
                fn ($q) => $q->where('estado', 'activo')
            )
            ->when(
                $this->filtroEstado === 'completado',
                fn ($q) => $q->where('estado', 'completado')
            )
            ->when(
                $this->filtroEstado === 'vencido',
                fn ($q) => $q
                    ->where('estado', 'activo')
                    ->where('fecha_prevista_devolucion', '<', now())
            )
            ->latest('fecha_prestamo')
            ->paginate(10);

        $departamentos = Departamento::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // Herramientas individuales sin préstamo pendiente.
        $herramientasIndividuales = Herramienta::query()
            ->where('activo', true)
            ->where('tipo_control', 'individual')
            ->where('cantidad_actual', '>=', 1)
            ->whereDoesntHave(
                'prestamos',
                fn ($q) => $q->where('estado', 'prestado')
            )
            ->with('marca')
            ->orderBy('nombre')
            ->get();

        // Herramientas controladas por cantidad.
        $herramientasCantidad = Herramienta::query()
            ->where('activo', true)
            ->where('tipo_control', 'cantidad')
            ->where('cantidad_actual', '>', 0)
            ->with(['marca', 'unidadMedida'])
            ->orderBy('nombre')
            ->get();

        // Calcular pendientes en una consulta agrupada.
        $pendientes = PrestamoDetalle::query()
            ->where('estado', 'prestado')
            ->whereNotNull('herramienta_id')
            ->select('herramienta_id')
            ->selectRaw(
                'SUM(cantidad_prestada - cantidad_devuelta) AS total_pendiente'
            )
            ->groupBy('herramienta_id')
            ->pluck('total_pendiente', 'herramienta_id');

        $disponiblesCantidad = [];

        foreach ($herramientasCantidad as $herramienta) {
            $pendiente = (float) (
                $pendientes[$herramienta->id] ?? 0
            );

            $disponiblesCantidad[$herramienta->id] = max(
                0,
                round(
                    (float) $herramienta->cantidad_actual - $pendiente,
                    2
                )
            );
        }

        $herramientasCantidad = $herramientasCantidad
            ->filter(fn ($h) => $disponiblesCantidad[$h->id] > 0
            );

        $equipos = Equipo::query()
            ->whereDoesntHave(
                'prestamos',
                fn ($q) => $q->where('estado', 'prestado')
            )
            ->with(['marca', 'tipoEquipo'])
            ->orderBy('numero_inventario')
            ->get();

        return view(
            'livewire.movimientos.prestamos.index',
            compact(
                'prestamos',
                'departamentos',
                'herramientasIndividuales',
                'herramientasCantidad',
                'disponiblesCantidad',
                'equipos'
            )
        );
    }
}
