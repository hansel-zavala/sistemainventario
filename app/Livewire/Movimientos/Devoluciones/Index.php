<?php

namespace App\Livewire\Movimientos\Devoluciones;

use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\PrestamoDevolucion;
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

    public string $filtroEstado = 'pendientes';

    public bool $mostrarModal = false;

    public ?int $prestamoId = null;

    public array $seleccionados = [];

    public array $cantidades = [];

    public array $observacionesDetalle = [];

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function abrirDevolucion(int $id): void
    {
        $prestamo = Prestamo::query()
            ->whereKey($id)
            ->where('estado', 'activo')
            ->whereHas('detalles', function ($query) {
                $query->where('estado', 'prestado');
            })
            ->with([
                'detalles' => fn ($query) => $query->where('estado', 'prestado'),
            ])
            ->firstOrFail();

        $this->prestamoId = $prestamo->id;
        $this->seleccionados = [];
        $this->cantidades = [];
        $this->observacionesDetalle = [];

        foreach ($prestamo->detalles as $detalle) {
            $this->seleccionados[$detalle->id] = false;

            $this->cantidades[$detalle->id] =
                $detalle->cantidad_pendiente == 1
                    ? '1'
                    : '';
        }

        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->prestamoId = null;
        $this->seleccionados = [];
        $this->cantidades = [];
        $this->observacionesDetalle = [];

        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'seleccionados' => ['required', 'array'],
            'seleccionados.*' => ['boolean'],
            'cantidades' => ['array'],
            'cantidades.*' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'observacionesDetalle' => ['array'],
            'observacionesDetalle.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'cantidades.*.numeric' => 'Ingrese una cantidad válida.',
            'cantidades.*.gt' => 'La cantidad debe ser mayor que cero.',
            'cantidades.*.decimal' => 'Solo se permiten hasta dos decimales.',
            'observacionesDetalle.*.max' => 'Las observaciones no pueden superar 1000 caracteres.',
        ];
    }

    public function guardarDevolucion(): void
    {
        if ($this->prestamoId === null) {
            $this->addError(
                'seleccionados',
                'Debe seleccionar un préstamo.'
            );

            return;
        }

        /*
         * Validar únicamente las cantidades seleccionadas.
         * Los campos de los activos no seleccionados se ignoran.
         */
        $this->validate([
            'seleccionados' => ['required', 'array'],
            'seleccionados.*' => ['boolean'],
            'observacionesDetalle' => ['array'],
            'observacionesDetalle.*' => [
                'nullable', 'string', 'max:1000',
            ],
        ]);

        $ids = collect($this->seleccionados)
            ->filter(fn ($valor) => $valor === true)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($ids) === 0) {
            $this->addError(
                'seleccionados',
                'Seleccione al menos un activo para devolver.'
            );

            return;
        }

        $cantidadesValidadas = [];

        foreach ($ids as $id) {
            $valor = $this->cantidades[$id] ?? null;

            $validador = validator(
                ['cantidad' => $valor],
                [
                    'cantidad' => [
                        'required',
                        'numeric',
                        'gt:0',
                        'decimal:0,2',
                    ],
                ]
            );

            if ($validador->fails()) {
                $this->addError(
                    "cantidades.$id",
                    'Ingrese una cantidad positiva de hasta dos decimales.'
                );

                continue;
            }

            $cantidadesValidadas[$id] = round(
                (float) $valor,
                2
            );
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use (
            $ids,
            $cantidadesValidadas
        ) {
            /*
             * Un solo usuario procesa este préstamo
             * a la vez.
             */
            $prestamo = Prestamo::query()
                ->whereKey($this->prestamoId)
                ->lockForUpdate()
                ->first();

            if (! $prestamo || $prestamo->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'seleccionados' => 'El préstamo ya no está activo.',
                ]);
            }

            $detalles = PrestamoDetalle::query()
                ->where('prestamo_id', $prestamo->id)
                ->whereIn('id', $ids)
                ->where('estado', 'prestado')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($detalles->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'seleccionados' => 'Uno de los activos ya fue devuelto o no pertenece a este préstamo.',
                ]);
            }

            $fecha = now();

            foreach ($detalles as $detalle) {
                $cantidad = $cantidadesValidadas[$detalle->id];
                $pendiente = $detalle->cantidad_pendiente;

                if ($cantidad > $pendiente) {
                    throw ValidationException::withMessages([
                        "cantidades.{$detalle->id}" => "Solo quedan {$pendiente} unidades pendientes.",
                    ]);
                }

                $esPorCantidad =
                    $detalle->herramienta_id !== null
                    && $detalle->herramienta?->tipo_control === 'cantidad';

                /*
                 * Equipos y herramientas individuales
                 * solo admiten devoluciones de 1 unidad.
                 */
                if (
                    ! $esPorCantidad
                    && abs($cantidad - 1) > 0.00001
                ) {
                    throw ValidationException::withMessages([
                        "cantidades.{$detalle->id}" => 'Este activo debe devolverse como una unidad.',
                    ]);
                }

                if ($esPorCantidad) {
                    $detalle->load('herramienta.unidadMedida');

                    $permiteDecimales =
                        (bool) $detalle->herramienta
                            ?->unidadMedida?->permite_decimales;

                    if (
                        ! $permiteDecimales
                        && abs($cantidad - round($cantidad)) > 0.00001
                    ) {
                        throw ValidationException::withMessages([
                            "cantidades.{$detalle->id}" => 'La unidad de medida no permite decimales.',
                        ]);
                    }
                }

                $nuevaCantidadDevuelta = round(
                    (float) $detalle->cantidad_devuelta
                    + $cantidad,
                    2
                );

                $completado = $nuevaCantidadDevuelta >=
                    (float) $detalle->cantidad_prestada;

                $observaciones = trim(
                    (string) (
                        $this->observacionesDetalle[$detalle->id]
                        ?? ''
                    )
                );

                /*
                 * Guardar el evento independiente.
                 */
                PrestamoDevolucion::create([
                    'prestamo_detalle_id' => $detalle->id,
                    'cantidad' => $cantidad,
                    'fecha_devolucion' => $fecha,
                    'recibido_por' => Auth::id(),
                    'observaciones' => $observaciones !== ''
                            ? $observaciones
                            : null,
                ]);

                /*
                 * Actualizar los acumulados.
                 */
                $detalle->update([
                    'cantidad_devuelta' => $nuevaCantidadDevuelta,
                    'estado' => $completado ? 'devuelto' : 'prestado',
                    'fecha_devolucion' => $completado ? $fecha : null,
                    'recibido_por' => $completado ? Auth::id() : null,
                    'observaciones_devolucion' => $completado
                            ? ($observaciones !== ''
                                ? $observaciones
                                : null)
                            : null,
                ]);
            }

            /*
             * El préstamo solo se completa
             * cuando todos sus detalles están devueltos.
             */
            $quedanPendientes = PrestamoDetalle::query()
                ->where('prestamo_id', $prestamo->id)
                ->where('estado', 'prestado')
                ->exists();

            if (! $quedanPendientes) {
                $prestamo->update([
                    'estado' => 'completado',
                ]);
            }
        }, 3);

        $this->cerrarModal();
        $this->resetPage();

        session()->flash(
            'mensaje',
            'Devolución registrada correctamente.'
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
                'detalles.devoluciones.usuario',
            ])
            ->when(
                $this->filtroEstado === 'pendientes',
                fn ($query) => $query
                    ->where('estado', 'activo')
                    ->whereHas(
                        'detalles',
                        fn ($q) => $q->where('estado', 'prestado')
                    )
            )
            ->when(
                $this->filtroEstado === 'completados',
                fn ($query) => $query
                    ->where('estado', 'completado')
            )
            ->when(
                trim($this->buscar) !== '',
                function ($query) {
                    $buscar = '%'.trim($this->buscar).'%';

                    $query->where(function ($q) use ($buscar) {
                        $q->where('persona_recibe', 'like', $buscar)
                            ->orWhereHas(
                                'departamentoDestino',
                                fn ($d) => $d->where(
                                    'nombre', 'like', $buscar
                                )
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
                                    ->where(
                                        'numero_inventario',
                                        'like',
                                        $buscar
                                    )
                                    ->orWhere(
                                        'modelo',
                                        'like',
                                        $buscar
                                    )
                            );
                    });
                }
            )
            ->latest('fecha_prestamo')
            ->paginate(10);

        $prestamoSeleccionado = null;

        if ($this->mostrarModal && $this->prestamoId !== null) {
            $prestamoSeleccionado = Prestamo::query()
                ->with([
                    'departamentoDestino',
                    'detalles' => fn ($query) => $query->where('estado', 'prestado'),
                    'detalles.herramienta.unidadMedida',
                    'detalles.equipo.marca',
                    'detalles.equipo.tipoEquipo',
                ])
                ->find($this->prestamoId);
        }

        return view(
            'livewire.movimientos.devoluciones.index',
            compact('prestamos', 'prestamoSeleccionado')
        );
    }
}
