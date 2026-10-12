<?php

namespace App\Livewire\Movimientos\Devoluciones;

use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
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

    // Claves: ID del detalle. Valores: true/false.
    public array $seleccionados = [];

    // Observaciones individuales de devolución.
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
            ->whereHas(
                'detalles',
                fn ($query) => $query->where('estado', 'prestado')
            )
            ->firstOrFail();

        $this->prestamoId = $prestamo->id;
        $this->seleccionados = [];
        $this->observacionesDetalle = [];

        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->prestamoId = null;
        $this->seleccionados = [];
        $this->observacionesDetalle = [];

        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'seleccionados' => [
                'required',
                'array',
            ],

            'seleccionados.*' => [
                'boolean',
            ],

            'observacionesDetalle' => [
                'array',
            ],

            'observacionesDetalle.*' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'seleccionados.array' =>
                'La selección de activos no es válida.',

            'seleccionados.*.boolean' =>
                'La selección de uno de los activos no es válida.',

            'observacionesDetalle.*.max' =>
                'Las observaciones no pueden superar 1000 caracteres.',
        ];
    }

    public function guardarDevolucion(): void
    {
        $this->validate();

        if ($this->prestamoId === null) {
            $this->addError(
                'seleccionados',
                'No se ha seleccionado un préstamo.'
            );

            return;
        }

        $idsSeleccionados = collect($this->seleccionados)
            ->filter(fn ($valor) => $valor === true)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($idsSeleccionados) === 0) {
            $this->addError(
                'seleccionados',
                'Seleccione al menos un activo para devolver.'
            );

            return;
        }

        DB::transaction(function () use ($idsSeleccionados) {
            /*
             * Bloqueamos primero el préstamo.
             * Así evitamos procesar simultáneamente
             * dos devoluciones del mismo préstamo.
             */
            $prestamo = Prestamo::query()
                ->whereKey($this->prestamoId)
                ->lockForUpdate()
                ->first();

            if (! $prestamo || $prestamo->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'seleccionados' =>
                        'El préstamo ya no se encuentra activo.',
                ]);
            }

            /*
             * Recuperamos y bloqueamos únicamente
             * los detalles pendientes seleccionados.
             */
            $detalles = PrestamoDetalle::query()
                ->where('prestamo_id', $prestamo->id)
                ->whereIn('id', $idsSeleccionados)
                ->where('estado', 'prestado')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($detalles->count() !== count($idsSeleccionados)) {
                throw ValidationException::withMessages([
                    'seleccionados' =>
                        'Uno de los activos ya fue devuelto o no pertenece al préstamo.',
                ]);
            }

            $fechaDevolucion = now();

            foreach ($detalles as $detalle) {
                $observacion = trim(
                    $this->observacionesDetalle[$detalle->id] ?? ''
                );

                $detalle->update([
                    'estado' => 'devuelto',

                    'fecha_devolucion' =>
                        $fechaDevolucion,

                    'recibido_por' =>
                        Auth::id(),

                    'observaciones_devolucion' =>
                        $observacion !== ''
                            ? $observacion
                            : null,
                ]);
            }

            /*
             * Si no queda ningún activo prestado,
             * el préstamo pasa a Completado.
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
                'detalles.usuarioRecibe',
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
                $this->buscar !== '',
                function ($query) {
                    $buscar = '%' . trim($this->buscar) . '%';

                    $query->where(function ($q) use ($buscar) {
                        $q->where('persona_recibe', 'like', $buscar)

                            ->orWhereHas(
                                'departamentoDestino',
                                fn ($d) => $d->where(
                                    'nombre',
                                    'like',
                                    $buscar
                                )
                            )

                            ->orWhereHas(
                                'detalles.herramienta',
                                function ($h) use ($buscar) {
                                    $h->where('nombre', 'like', $buscar)
                                        ->orWhere(
                                            'codigo_interno',
                                            'like',
                                            $buscar
                                        );
                                }
                            )

                            ->orWhereHas(
                                'detalles.equipo',
                                function ($e) use ($buscar) {
                                    $e->where(
                                        'numero_inventario',
                                        'like',
                                        $buscar
                                    )->orWhere(
                                        'modelo',
                                        'like',
                                        $buscar
                                    );
                                }
                            );
                    });
                }
            )

            ->latest('fecha_prestamo')
            ->paginate(10);

        /*
         * Cargamos aparte el préstamo del modal.
         * Solo incluimos activos pendientes.
         */
        $prestamoSeleccionado = null;

        if ($this->mostrarModal && $this->prestamoId) {
            $prestamoSeleccionado = Prestamo::query()
                ->with([
                    'departamentoDestino',
                    'detalles' => fn ($query) => $query
                        ->where('estado', 'prestado'),

                    'detalles.herramienta.marca',
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
