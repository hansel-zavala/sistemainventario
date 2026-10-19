<?php

namespace App\Livewire\Historial\Movimientos;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';
    public string $filtroTipo = '';
    public string $fechaDesde = '';
    public string $fechaHasta = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatedFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'buscar',
            'filtroTipo',
            'fechaDesde',
            'fechaHasta',
        ]);

        $this->resetPage();
    }

    private function construirEventos()
    {
        
        // Rehacer las uniones según tipo_item.
        // Así un ID repetido en distintas tablas
        // nunca identifica un artículo incorrecto.
        $inventario = DB::table(
            'movimiento_inventario_detalles as d'
        )
            ->join(
                'movimientos_inventario as m',
                'm.id',
                '=',
                'd.movimiento_inventario_id'
            )
            ->leftJoin('herramientas as h', function ($join) {
                $join->on('h.id', '=', 'd.item_id')
                    ->where('d.tipo_item', '=', 'herramienta');
            })
            ->leftJoin('insumos as i', function ($join) {
                $join->on('i.id', '=', 'd.item_id')
                    ->where('d.tipo_item', '=', 'insumo');
            })
            ->leftJoin('tintas as t', function ($join) {
                $join->on('t.id', '=', 'd.item_id')
                    ->where('d.tipo_item', '=', 'tinta');
            })
            ->whereIn('m.tipo', [
                'apertura',
                'entrada',
                'salida',
            ])
            ->selectRaw("
                m.fecha_movimiento AS fecha,
                m.tipo AS tipo,
                d.tipo_item AS tipo_item,
                d.item_id AS item_id,
                CASE
                    WHEN d.tipo_item = 'herramienta'
                        THEN COALESCE(h.nombre, 'Herramienta no disponible')
                    WHEN d.tipo_item = 'insumo'
                        THEN COALESCE(i.nombre, 'Insumo no disponible')
                    WHEN d.tipo_item = 'tinta'
                        THEN CONCAT(
                            COALESCE(t.nombre, 'Tinta no disponible'),
                            ' - ',
                            COALESCE(t.color, '')
                        )
                    ELSE 'Articulo no disponible'
                END AS articulo,
                d.cantidad AS cantidad,
                CASE
                    WHEN d.tipo_item = 'tinta'
                        THEN 'botellas'
                    ELSE 'unidades'
                END AS unidad,
                d.existencia_anterior AS anterior,
                d.existencia_posterior AS posterior,
                m.registrado_por AS responsable_id,
                COALESCE(m.observaciones, '') AS detalle,
                d.id AS origen_id
            ");

        // 2. Préstamos de herramientas y equipos.
        $prestamos = DB::table('prestamo_detalles as pd')
            ->join('prestamos as p', 'p.id', '=', 'pd.prestamo_id')
            ->leftJoin(
                'herramientas as h',
                'h.id',
                '=',
                'pd.herramienta_id'
            )
            ->leftJoin(
                'equipos as e',
                'e.id',
                '=',
                'pd.equipo_id'
            )
            ->leftJoin(
                'departamentos as dep',
                'dep.id',
                '=',
                'p.departamento_destino_id'
            )
            ->selectRaw("
                p.fecha_prestamo AS fecha,
                'prestamo' AS tipo,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN 'herramienta'
                    ELSE 'equipo'
                END AS tipo_item,
                COALESCE(pd.herramienta_id, pd.equipo_id) AS item_id,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN COALESCE(h.nombre, 'Herramienta no disponible')
                    ELSE CONCAT(
                        'Equipo: ',
                        COALESCE(
                            e.numero_inventario,
                            e.numero_serie,
                            e.modelo,
                            'Sin identificacion'
                        )
                    )
                END AS articulo,
                pd.cantidad_prestada AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                p.registrado_por AS responsable_id,
                CONCAT(
                    'Prestamo #', p.id,
                    ' | Departamento: ',
                    COALESCE(dep.nombre, 'No disponible'),
                    ' | Recibe: ',
                    COALESCE(p.persona_recibe, 'No especificado')
                ) AS detalle,
                pd.id AS origen_id
            ");

        // 3. Devoluciones parciales y completas.
        $devoluciones = DB::table(
            'prestamo_devoluciones as dv'
        )
            ->join(
                'prestamo_detalles as pd',
                'pd.id',
                '=',
                'dv.prestamo_detalle_id'
            )
            ->join(
                'prestamos as p',
                'p.id',
                '=',
                'pd.prestamo_id'
            )
            ->leftJoin(
                'herramientas as h',
                'h.id',
                '=',
                'pd.herramienta_id'
            )
            ->leftJoin(
                'equipos as e',
                'e.id',
                '=',
                'pd.equipo_id'
            )
            ->selectRaw("
                dv.fecha_devolucion AS fecha,
                'devolucion' AS tipo,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN 'herramienta'
                    ELSE 'equipo'
                END AS tipo_item,
                COALESCE(pd.herramienta_id, pd.equipo_id) AS item_id,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN COALESCE(h.nombre, 'Herramienta no disponible')
                    ELSE CONCAT(
                        'Equipo: ',
                        COALESCE(
                            e.numero_inventario,
                            e.numero_serie,
                            e.modelo,
                            'Sin identificacion'
                        )
                    )
                END AS articulo,
                dv.cantidad AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                dv.recibido_por AS responsable_id,
                CONCAT(
                    'Devolucion del prestamo #', p.id,
                    ' | ',
                    COALESCE(dv.observaciones, '')
                ) AS detalle,
                dv.id AS origen_id
            ");

        // 4. Recargas de tinta.
        $recargas = DB::table(
            'recarga_tinta_detalles as rd'
        )
            ->join(
                'recargas_tinta as r',
                'r.id',
                '=',
                'rd.recarga_tinta_id'
            )
            ->leftJoin(
                'tintas as t',
                't.id',
                '=',
                'rd.tinta_id'
            )
            ->leftJoin(
                'equipos as e',
                'e.id',
                '=',
                'r.equipo_id'
            )
            ->selectRaw("
                r.created_at AS fecha,
                'recarga' AS tipo,
                'tinta' AS tipo_item,
                rd.tinta_id AS item_id,
                CONCAT(
                    COALESCE(t.nombre, 'Tinta no disponible'),
                    ' - ',
                    COALESCE(t.color, '')
                ) AS articulo,
                rd.porcentaje_agregado AS cantidad,
                '% de botella' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                r.realizado_por AS responsable_id,
                CONCAT(
                    'Recarga #', r.id,
                    ' | Impresora: ',
                    COALESCE(
                        e.numero_inventario,
                        e.numero_serie,
                        e.modelo,
                        'Sin identificacion'
                    ),
                    ' | Nivel de impresora: ',
                    rd.porcentaje_antes,
                    '% a ',
                    rd.porcentaje_despues,
                    '% | ',
                    COALESCE(r.observaciones, '')
                ) AS detalle,
                rd.id AS origen_id
            ");

        /*
         * 5. Devoluciones antiguas.
         * Estas se registraron antes de crear
         * la tabla de eventos de devolución.
         */
        $devolucionesAntiguas = DB::table(
            'prestamo_detalles as pd'
        )
            ->join(
                'prestamos as p',
                'p.id',
                '=',
                'pd.prestamo_id'
            )
            ->leftJoin(
                'herramientas as h',
                'h.id',
                '=',
                'pd.herramienta_id'
            )
            ->leftJoin(
                'equipos as e',
                'e.id',
                '=',
                'pd.equipo_id'
            )
            ->where('pd.estado', 'devuelto')
            ->whereNotNull('pd.fecha_devolucion')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('prestamo_devoluciones as dv')
                    ->whereColumn(
                        'dv.prestamo_detalle_id',
                        'pd.id'
                    );
            })
            ->selectRaw("
                pd.fecha_devolucion AS fecha,
                'devolucion' AS tipo,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN 'herramienta'
                    ELSE 'equipo'
                END AS tipo_item,
                COALESCE(pd.herramienta_id, pd.equipo_id) AS item_id,
                CASE
                    WHEN pd.herramienta_id IS NOT NULL
                        THEN COALESCE(h.nombre, 'Herramienta no disponible')
                    ELSE CONCAT(
                        'Equipo: ',
                        COALESCE(
                            e.numero_inventario,
                            e.numero_serie,
                            e.modelo,
                            'Sin identificacion'
                        )
                    )
                END AS articulo,
                pd.cantidad_devuelta AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                pd.recibido_por AS responsable_id,
                CONCAT(
                    'Devolucion historica del prestamo #',
                    p.id
                ) AS detalle,
                pd.id AS origen_id
            ");

        return $inventario
            ->unionAll($prestamos)
            ->unionAll($devoluciones)
            ->unionAll($recargas)
            ->unionAll($devolucionesAntiguas);
    }

    public function render()
    {
        $tipos = [
            'apertura',
            'entrada',
            'salida',
            'prestamo',
            'devolucion',
            'recarga',
        ];

        // Todos los eventos se consultan como una sola tabla.
        $query = DB::query()
            ->fromSub($this->construirEventos(), 'eventos')
            ->leftJoin(
                'users as u',
                'u.id',
                '=',
                'eventos.responsable_id'
            )
            ->select(
                'eventos.*',
                'u.name as responsable'
            );

        // Búsqueda por artículo, detalle o responsable.
        $buscar = trim($this->buscar);

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $texto = '%' . $buscar . '%';

                $q->where('eventos.articulo', 'like', $texto)
                    ->orWhere('eventos.detalle', 'like', $texto)
                    ->orWhere('u.name', 'like', $texto);
            });
        }

        if ($this->filtroTipo !== '') {
            if (in_array($this->filtroTipo, $tipos, true)) {
                $query->where(
                    'eventos.tipo',
                    $this->filtroTipo
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Validar fechas antes de utilizarlas.
        if (
            $this->fechaDesde !== ''
            && preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $this->fechaDesde
            )
        ) {
            $query->whereDate(
                'eventos.fecha',
                '>=',
                $this->fechaDesde
            );
        }

        if (
            $this->fechaHasta !== ''
            && preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $this->fechaHasta
            )
        ) {
            $query->whereDate(
                'eventos.fecha',
                '<=',
                $this->fechaHasta
            );
        }

        $eventosHistorial = $query
            ->orderByDesc('eventos.fecha')
            ->orderByDesc('eventos.tipo')
            ->orderByDesc('eventos.origen_id')
            ->paginate(15);

        return view(
            'livewire.historial.movimientos.index',
            compact('eventosHistorial')
        );
    }
}
