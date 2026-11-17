<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class SalidasTable extends MovimientosTable
{
    public const NO_DESTINATION = 'sin-destino';

    public const COLUMNS = [
        'salida' => 'N.º de salida', 'fecha' => 'Fecha del movimiento', 'usuario' => 'Registrado por',
        'destino' => 'Departamento destino', 'receptor' => 'Persona que recibe', 'motivo' => 'Motivo',
        'tipo' => 'Tipo de artículo', 'articulo' => 'Artículo', 'anterior' => 'Existencia anterior',
        'cantidad' => 'Cantidad retirada', 'posterior' => 'Existencia posterior', 'observaciones' => 'Observaciones',
    ];

    public const FILTER_MESSAGES = [
        ...parent::FILTER_MESSAGES,
        'destino.integer' => 'Seleccione un departamento destino válido.',
        'destino.exists' => 'Seleccione un departamento destino válido.',
    ];

    public static function filterRules(array $filters): array
    {
        return [
            ...parent::filterRules($filters),
            'destino' => ($filters['destino'] ?? null) === self::NO_DESTINATION
                ? ['nullable', Rule::in([self::NO_DESTINATION])]
                : ['nullable', 'bail', 'integer', 'exists:departamentos,id'],
        ];
    }

    public function query(array $filters): Builder
    {
        $query = $this->movementQuery('salida', $filters)->with('departamentoDestino:id,nombre,sigla');
        $destination = $filters['destino'] ?? '';

        if ($destination === self::NO_DESTINATION) {
            $query->whereNull('departamento_destino_id');
        } elseif ($destination !== '') {
            $query->where('departamento_destino_id', (int) $destination);
        }

        return $query;
    }

    public static function destinationName(?Departamento $department): string
    {
        if ($department === null) {
            return 'Sin destino';
        }

        return $department->sigla ? $department->nombre.' ('.$department->sigla.')' : $department->nombre;
    }

    protected function exportValues(MovimientoInventario $movement, MovimientoInventarioDetalle $detail): array
    {
        return [
            ...parent::exportValues($movement, $detail),
            'salida' => (string) $movement->id,
            'destino' => self::destinationName($movement->departamentoDestino),
            'receptor' => $movement->persona_recibe ?? '',
            'motivo' => $movement->motivo ?? '',
        ];
    }
}
