<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Equipo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use stdClass;

class HistorialMovimientosTable
{
    public const TYPES = ['apertura' => 'Apertura', 'entrada' => 'Entrada', 'salida' => 'Salida', 'prestamo' => 'Préstamo', 'devolucion' => 'Devolución', 'recarga' => 'Recarga'];

    public const ITEMS = ['equipo' => 'Equipo tecnológico', 'herramienta' => 'Herramienta', 'insumo' => 'Insumo', 'tinta' => 'Tinta'];

    public const COLUMNS = ['fecha' => 'Fecha', 'tipo' => 'Movimiento', 'articulo' => 'Artículo', 'cantidad' => 'Cantidad', 'responsable' => 'Responsable', 'detalle' => 'Detalles'];

    public const EXPORT_COLUMNS = [
        'fecha' => 'Fecha', 'tipo' => 'Movimiento', 'articulo' => 'Artículo', 'tipo_item' => 'Tipo de artículo',
        'cantidad' => 'Cantidad', 'unidad' => 'Unidad', 'responsable' => 'Responsable',
        'anterior' => 'Existencia anterior', 'posterior' => 'Existencia posterior', 'detalle' => 'Detalles',
    ];

    public static function filterRules(array $filters): array
    {
        $rules = MovimientosTable::filterRules($filters);
        unset($rules['tipoArticulo']);

        return [
            ...$rules,
            'buscar' => ['nullable', 'string', 'max:200'],
            'filtroTipo' => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
            'filtroArticuloTipo' => ['nullable', 'string', Rule::in(array_keys(self::ITEMS))],
            'filtroResponsable' => ['nullable', 'bail', 'integer', 'exists:users,id'],
        ];
    }

    public function query(array $filters): Builder
    {
        $query = DB::query()->fromSub($this->events(), 'eventos')
            ->leftJoin('users as u', 'u.id', '=', 'eventos.responsable_id')
            ->select('eventos.*', 'u.name as responsable');

        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $events) => $events->where('eventos.articulo', 'like', $term)
                ->orWhere('eventos.identificadores', 'like', $term)
                ->orWhere('eventos.detalle', 'like', $term)
                ->orWhere('u.name', 'like', $term));
        }

        foreach (['filtroTipo' => 'tipo', 'filtroArticuloTipo' => 'tipo_item', 'filtroResponsable' => 'responsable_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where('eventos.'.$column, $filter === 'filtroResponsable' ? (int) $filters[$filter] : $filters[$filter]);
            }
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('eventos.fecha', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('eventos.fecha', '<=', $filters['hasta']);
        }

        $direction = ($filters['orden'] ?? MovimientosTable::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';
        $query->orderBy('eventos.fecha', $direction)->orderBy('eventos.tipo', $direction)
            ->orderBy('eventos.origen_id', $direction)->orderBy('eventos.fuente', $direction);

        return $query;
    }

    public function exportRows(array $filters, array $columns): LazyCollection
    {
        return $this->query($filters)->lazy(500)->map(function (stdClass $event) use ($columns): array {
            $values = [
                'fecha' => Carbon::parse($event->fecha)->format('d/m/Y H:i'),
                'tipo' => self::TYPES[$event->tipo] ?? $event->tipo,
                'articulo' => $event->articulo,
                'tipo_item' => self::ITEMS[$event->tipo_item] ?? $event->tipo_item,
                'cantidad' => $this->quantity($event->cantidad),
                'unidad' => $event->unidad,
                'responsable' => $event->responsable ?? 'Usuario no disponible',
                'anterior' => $this->quantity($event->anterior),
                'posterior' => $this->quantity($event->posterior),
                'detalle' => $event->detalle,
            ];

            return array_map(fn (string $column): string => $values[$column], $columns);
        });
    }

    public function equipmentTimeline(int $equipoId): Builder
    {
        $disponibilidad = DB::query()->fromSub($this->events($equipoId), 'disponibilidad')
            ->select('fecha', 'tipo', 'responsable_id', 'origen_id', 'fuente');

        $cambios = Auditoria::query()
            ->where('modelo', Equipo::class)
            ->where('registro_id', $equipoId)
            ->whereIn('accion', ['actualizacion', 'activacion', 'desactivacion'])
            ->selectRaw("created_at as fecha, accion as tipo, usuario_id as responsable_id, id as origen_id, 'auditoria' as fuente");

        return DB::query()->fromSub($disponibilidad->unionAll($cambios->toBase()), 'eventos')
            ->leftJoin('users as u', 'u.id', '=', 'eventos.responsable_id')
            ->select('eventos.fecha', 'eventos.tipo', 'eventos.origen_id', 'eventos.fuente', 'u.name as responsable')
            ->orderByDesc('eventos.fecha')
            ->orderByDesc('eventos.origen_id')
            ->orderBy('eventos.fuente');
    }

    private function quantity(string|int|float|null $value): string
    {
        return $value === null ? '' : number_format((float) $value, 2, '.', '');
    }

    private function concat(string ...$expressions): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return '('.implode(' || ', $expressions).')';
        }

        return 'CONCAT('.implode(', ', $expressions).')';
    }

    private function events(?int $equipoId = null): Builder
    {
        $inkLabel = $this->concat("COALESCE(t.nombre, 'Tinta no disponible')", "' - '", "COALESCE(t.color, '')");
        $equipmentCode = "COALESCE(e.numero_inventario, e.numero_serie, e.modelo, 'Sin identificacion')";
        $equipmentLabel = $this->concat("'Equipo: '", $equipmentCode);
        $equipmentIdentifiers = $this->concat("COALESCE(e.numero_inventario, '')", "' '", "COALESCE(e.numero_serie, '')", "' '", "COALESCE(e.modelo, '')");
        $assetIdentifiers = $this->concat("COALESCE(h.codigo_interno, '')", "' '", $equipmentIdentifiers);
        $loanDescription = $this->concat("'Prestamo #'", 'p.id', "' | Departamento: '", "COALESCE(dep.nombre, 'No disponible')",
            "' | Recibe: '", "COALESCE(p.persona_recibe, 'No especificado')");
        $returnDescription = $this->concat("'Devolucion del prestamo #'", 'p.id', "' | '", "COALESCE(dv.observaciones, '')");
        $refillDescription = $this->concat("'Recarga #'", 'r.id', "' | Impresora: '", $equipmentCode,
            "' | Nivel de impresora: '", 'rd.porcentaje_antes', "'% a '", 'rd.porcentaje_despues', "'% | '", "COALESCE(r.observaciones, '')");
        $legacyDescription = $this->concat("'Devolucion historica del prestamo #'", 'p.id');

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
                        THEN {$inkLabel}
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
                d.id AS origen_id,
                'inventario' AS fuente,
                COALESCE(h.codigo_interno, '') AS identificadores
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
                    ELSE {$equipmentLabel}
                END AS articulo,
                pd.cantidad_prestada AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                p.registrado_por AS responsable_id,
                {$loanDescription} AS detalle,
                pd.id AS origen_id,
                'prestamos' AS fuente,
                {$assetIdentifiers} AS identificadores
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
                    ELSE {$equipmentLabel}
                END AS articulo,
                dv.cantidad AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                dv.recibido_por AS responsable_id,
                {$returnDescription} AS detalle,
                dv.id AS origen_id,
                'devoluciones' AS fuente,
                {$assetIdentifiers} AS identificadores
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
                {$inkLabel} AS articulo,
                rd.porcentaje_agregado AS cantidad,
                '% de botella' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                r.realizado_por AS responsable_id,
                {$refillDescription} AS detalle,
                rd.id AS origen_id,
                'recargas' AS fuente,
                {$equipmentIdentifiers} AS identificadores
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
                    ELSE {$equipmentLabel}
                END AS articulo,
                pd.cantidad_devuelta AS cantidad,
                'unidades' AS unidad,
                NULL AS anterior,
                NULL AS posterior,
                pd.recibido_por AS responsable_id,
                {$legacyDescription} AS detalle,
                pd.id AS origen_id,
                'devoluciones_historicas' AS fuente,
                {$assetIdentifiers} AS identificadores
            ");

        if ($equipoId !== null) {
            foreach ([$prestamos, $devoluciones, $devolucionesAntiguas] as $evento) {
                $evento->where('pd.equipo_id', $equipoId)->whereNull('pd.herramienta_id');
            }

            return $prestamos->unionAll($devoluciones)->unionAll($devolucionesAntiguas);
        }

        return $inventario
            ->unionAll($prestamos)
            ->unionAll($devoluciones)
            ->unionAll($recargas)
            ->unionAll($devolucionesAntiguas);
    }
}
