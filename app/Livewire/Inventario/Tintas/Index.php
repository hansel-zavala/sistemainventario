<?php

namespace App\Livewire\Inventario\Tintas;

use App\Models\Marca;
use App\Models\Tinta;
use App\Models\MovimientoInventarioDetalle;
use App\Models\RecargaTintaDetalle;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Services\RegistrarAperturaInventario;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public bool $mostrarHistorial = false;

    public ?int $historialTintaId = null;

    public string $historialTintaNombre = '';

    public string $buscar = '';

    public string $filtroColor = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $tintaId = null;

    public string $nombre = '';

    public string $marcaId = '';

    public string $color = '';

    public string $presentacion = '';

    public string $botellasCompletas = '0';

    public string $porcentajeBotellaAbierta = '0';

    public string $stockMinimoBotellas = '1';

    public string $observaciones = '';

    public bool $activoTinta = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroColor(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearTinta(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarTinta(int $id): void
    {
        $tinta = Tinta::findOrFail($id);

        $this->tintaId = $tinta->id;
        $this->nombre = $tinta->nombre;

        $this->marcaId = $tinta->marca_id
            ? (string) $tinta->marca_id
            : '';

        $this->color = $tinta->color;

        $this->presentacion =
            $tinta->presentacion ?? '';

        $this->botellasCompletas =
            (string) $tinta->botellas_completas;

        $this->porcentajeBotellaAbierta =
            (string) $tinta->porcentaje_botella_abierta;

        $this->stockMinimoBotellas =
            (string) $tinta->stock_minimo_botellas;

        $this->observaciones =
            $tinta->observaciones ?? '';

        $this->activoTinta =
            $tinta->activo;

        $this->resetValidation();

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
        $this->tintaId = null;
        $this->nombre = '';
        $this->marcaId = '';
        $this->color = '';
        $this->presentacion = '';
        $this->botellasCompletas = '0';
        $this->porcentajeBotellaAbierta = '0';
        $this->stockMinimoBotellas = '1';
        $this->observaciones = '';
        $this->activoTinta = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'marcaId' => [
                'nullable',
                'exists:marcas,id',
            ],

            'color' => [
                'required',
                'string',
                'max:50',
            ],

            'presentacion' => [
                'nullable',
                'string',
                'max:100',
            ],

            'botellasCompletas' => [
                'required',
                'integer',
                'min:0',
            ],

            'porcentajeBotellaAbierta' => [
                'required',
                'numeric',
                'min:0',
                'max:99.99',
            ],

            'stockMinimoBotellas' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'activoTinta' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
            'El nombre o referencia de la tinta es obligatorio.',

            'marcaId.exists' =>
            'La marca seleccionada no es válida.',

            'color.required' =>
            'El color de la tinta es obligatorio.',

            'botellasCompletas.required' =>
            'Debe indicar la cantidad de botellas completas.',

            'botellasCompletas.integer' =>
            'Las botellas completas deben registrarse con un número entero.',

            'botellasCompletas.min' =>
            'La cantidad de botellas no puede ser negativa.',

            'porcentajeBotellaAbierta.required' =>
            'Debe indicar el porcentaje restante de la botella abierta.',

            'porcentajeBotellaAbierta.numeric' =>
            'El porcentaje de la botella abierta debe ser un número.',

            'porcentajeBotellaAbierta.min' =>
            'El porcentaje no puede ser negativo.',

            'porcentajeBotellaAbierta.max' =>
            'La botella abierta debe tener un valor menor de 100 %.',

            'stockMinimoBotellas.required' =>
            'Debe indicar el stock mínimo.',

            'stockMinimoBotellas.numeric' =>
            'El stock mínimo debe ser un número.',

            'stockMinimoBotellas.min' =>
            'El stock mínimo no puede ser negativo.',

            'observaciones.max' =>
            'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }



    public function guardarTinta(): void
    {
        $this->validate();

        $esCreacion = $this->tintaId === null;

        $nombre = trim($this->nombre);
        $color = trim($this->color);

        $marcaId = $this->marcaId !== ''
            ? (int) $this->marcaId
            : null;

        $botellasIniciales = (int) $this->botellasCompletas;

        $porcentajeInicial = (float) $this->porcentajeBotellaAbierta;

        $stockMinimo = (float) $this->stockMinimoBotellas;

        DB::transaction(function () use (
            $esCreacion,
            $nombre,
            $color,
            $marcaId,
            $botellasIniciales,
            $porcentajeInicial,
            $stockMinimo
        ) {

            /* Obtener y bloquear la tinta cuando estamos editando */

            $tinta = null;

            if (! $esCreacion) {

                $tinta = Tinta::query()
                    ->whereKey($this->tintaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar que no exista una tinta duplicada */

            $duplicada = Tinta::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombre)]
                )
                ->whereRaw(
                    'LOWER(TRIM(color)) = ?',
                    [mb_strtolower($color)]
                )
                ->when(
                    $marcaId !== null,
                    fn($query) => $query->where(
                        'marca_id',
                        $marcaId
                    ),
                    fn($query) => $query->whereNull(
                        'marca_id'
                    )
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->tintaId
                    )
                )
                ->exists();

            if ($duplicada) {

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'nombre' =>
                    'Ya existe una tinta con esta referencia, marca y color.',
                ]);
            }

            /* Preparar datos generales */

            /* No incluimos las existencias de tinta porque al editar deben conservarse.*/

            $datos = [
                'nombre' => $nombre,

                'marca_id' => $marcaId,

                'color' => $color,

                'presentacion' =>
                trim($this->presentacion) !== ''
                    ? trim($this->presentacion)
                    : null,

                'stock_minimo_botellas' => $stockMinimo,

                'observaciones' =>
                trim($this->observaciones) !== ''
                    ? trim($this->observaciones)
                    : null,

                'activo' => $this->activoTinta,
            ];

            /* Crear una nueva tinta */



            if ($esCreacion) {

                $datos['botellas_completas'] = $botellasIniciales;
                $datos['porcentaje_botella_abierta'] = $porcentajeInicial;
                $datos['creado_por'] = Auth::id();

                $tinta = Tinta::create($datos);

                $existenciaEquivalente = $botellasIniciales
                    + ($porcentajeInicial / 100);

                RegistrarAperturaInventario::registrar(
                    'tinta',
                    $tinta->id,
                    $existenciaEquivalente,
                    'Apertura inicial de tinta: '
                        . $tinta->nombre
                        . ' | Color: ' . $tinta->color
                        . ' | Botellas completas: ' . $botellasIniciales
                        . ' | Porcentaje de botella abierta: '
                        . number_format($porcentajeInicial, 2, '.', '')
                        . '%'
                );

                RegistrarAuditoria::creacion($tinta, [
                    'nombre',
                    'marca_id',
                    'color',
                    'presentacion',
                    'botellas_completas',
                    'porcentaje_botella_abierta',
                    'stock_minimo_botellas',
                    'observaciones',
                    'activo',
                ]);
            } else {
                $tinta->fill($datos);

                RegistrarAuditoria::guardarActualizacion($tinta);
            }
        }, 3);

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Tinta registrada correctamente.'
                : 'Tinta actualizada correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }




    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            /* Obtener y bloquear la tinta */

            $tinta = Tinta::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $tinta->activo;

            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar estado */

            $tinta->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar auditoría de estado */

            RegistrarAuditoria::cambioEstado(
                $tinta,
                $estadoAnterior,
                $nuevoEstado
            );

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Tinta activada correctamente.'
                : 'Tinta desactivada correctamente.'
        );
    }



    public function abrirHistorial(int $id): void
    {
        $tinta = Tinta::findOrFail($id);

        $this->historialTintaId = $tinta->id;

        $this->historialTintaNombre =
            $tinta->nombre . ' — ' . $tinta->color;

        $this->mostrarHistorial = true;
    }

    public function cerrarHistorial(): void
    {
        $this->mostrarHistorial = false;

        $this->historialTintaId = null;

        $this->historialTintaNombre = '';
    }


    public function render()
    {
        $tintas = Tinta::query()
            ->with('marca')

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
                                ->orWhere(
                                    'color',
                                    'like',
                                    $buscar
                                )
                                ->orWhere(
                                    'presentacion',
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
                                );
                        }
                    );
                }
            )

            ->when(
                $this->filtroColor,
                function ($query) {
                    $query->where(
                        'color',
                        $this->filtroColor
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


        $marcas = Marca::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();


        $colores = Tinta::query()
            ->select('color')
            ->distinct()
            ->orderBy('color')
            ->pluck('color');


        /* Historial completo de tinta */

        $eventosHistorial = collect();

        if (
            $this->mostrarHistorial
            && $this->historialTintaId !== null
        ) {
            $tintaId = $this->historialTintaId;

            /* Aperturas, Entradas y Salidas */

            $movimientos = MovimientoInventarioDetalle::query()
                ->with([
                    'movimiento.usuario',
                ])
                ->where('tipo_item', 'tinta')
                ->where('item_id', $tintaId)
                ->whereHas('movimiento', function ($query) {
                    $query->whereIn('tipo', [
                        'apertura',
                        'entrada',
                        'salida',
                    ]);
                })
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            foreach ($movimientos as $detalle) {

                $movimiento = $detalle->movimiento;

                if (! $movimiento) {
                    continue;
                }

                $eventosHistorial->push([
                    'tipo' => $movimiento->tipo,
                    'fecha' => $movimiento->fecha_movimiento,

                    'cantidad' => (float) $detalle->cantidad,
                    'unidad' => 'botellas',

                    'anterior' => (float) $detalle->existencia_anterior,
                    'posterior' => (float) $detalle->existencia_posterior,

                    'responsable' => $movimiento->usuario?->name
                        ?? 'Usuario no disponible',

                    'detalle' => $movimiento->observaciones ?? '',

                    'orden_tipo' => 1,
                    'orden_id' => $detalle->id,
                ]);
            }

            /* Recargas de impresoras */

            $recargas = RecargaTintaDetalle::query()
                ->with([
                    'recarga.usuario',
                    'recarga.equipo',
                ])
                ->where('tinta_id', $tintaId)
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            foreach ($recargas as $detalleRecarga) {

                $recarga = $detalleRecarga->recarga;

                if (! $recarga) {
                    continue;
                }

                $equipo = $recarga->equipo;

                $identificacionEquipo = $equipo
                    ? (
                        $equipo->numero_inventario
                        ?: ($equipo->numero_serie ?: (
                            $equipo->modelo ?: 'Sin identificación'
                        ))
                    )
                    : 'Equipo no disponible';

                $nivelAntes = (float) $detalleRecarga->porcentaje_antes;

                $nivelDespues = (float) $detalleRecarga->porcentaje_despues;

                $observaciones = trim(
                    (string) ($recarga->observaciones ?? '')
                );

                $descripcion = 'Impresora: '
                    . $identificacionEquipo
                    . ' | Nivel antes: '
                    . number_format($nivelAntes, 2, '.', '')
                    . '% | Nivel después: '
                    . number_format($nivelDespues, 2, '.', '')
                    . '%';

                if ($observaciones !== '') {
                    $descripcion .= ' | ' . $observaciones;
                }

                $eventosHistorial->push([
                    'tipo' => 'recarga',

                    'fecha' => $recarga->created_at,

                    'cantidad' =>
                    (float) $detalleRecarga->porcentaje_agregado,

                    'unidad' => '% de una botella',

                    // Estos campos corresponden al inventario.
                    // El detalle de recarga no almacena los saldos
                    // anteriores y posteriores del almacén.
                    'anterior' => null,
                    'posterior' => null,

                    'responsable' => $recarga->usuario?->name
                        ?? 'Usuario no disponible',

                    'detalle' => $descripcion,

                    'orden_tipo' => 2,
                    'orden_id' => $detalleRecarga->id,
                ]);
            }

            /* Unificar y ordenar todos los eventos */

            $eventosHistorial = $eventosHistorial
                ->sort(function ($a, $b) {

                    $fechaA = \Illuminate\Support\Carbon::parse(
                        $a['fecha']
                    )->getTimestamp();

                    $fechaB = \Illuminate\Support\Carbon::parse(
                        $b['fecha']
                    )->getTimestamp();

                    if ($fechaA !== $fechaB) {
                        return $fechaB <=> $fechaA;
                    }

                    if ($a['orden_tipo'] !== $b['orden_tipo']) {
                        return $b['orden_tipo'] <=> $a['orden_tipo'];
                    }

                    return $b['orden_id'] <=> $a['orden_id'];
                })
                ->take(50)
                ->values();
        }


        return view(
            'livewire.inventario.tintas.index',
            compact(
                'tintas',
                'marcas',
                'colores',
                'eventosHistorial'
            )
        );
    }
}
