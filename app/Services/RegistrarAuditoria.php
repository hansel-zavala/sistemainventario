<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RegistrarAuditoria
{
    /* Registrar creación */

    public static function creacion(
        Model $modelo,
        array $campos
    ): void {
        if (! $modelo->exists) {
            throw new \LogicException(
                'No se puede auditar un registro que no ha sido guardado.'
            );
        }

        self::registrar(
            $modelo,
            'creacion',
            null,
            $modelo->only($campos)
        );
    }

    /* Guardar actualización y registrar cambios */

    public static function guardarActualizacion(
        Model $modelo
    ): bool {
        if (! $modelo->exists) {
            throw new \LogicException(
                'No se puede actualizar un registro inexistente.'
            );
        }

        $valoresNuevos = $modelo->getDirty();

        if (empty($valoresNuevos)) {
            return false;
        }

        $valoresAnteriores = [];

        foreach ($valoresNuevos as $campo => $valor) {
            $valoresAnteriores[$campo] =
                $modelo->getOriginal($campo);
        }

        $modelo->save();

        self::registrar(
            $modelo,
            'actualizacion',
            $valoresAnteriores,
            $valoresNuevos
        );

        return true;
    }

    /* Registrar activación o desactivación */

    public static function cambioEstado(
        Model $modelo,
        bool $estadoAnterior,
        bool $estadoNuevo
    ): void {
        if ($estadoAnterior === $estadoNuevo) {
            return;
        }

        self::registrar(
            $modelo,
            $estadoNuevo ? 'activacion' : 'desactivacion',
            ['activo' => $estadoAnterior],
            ['activo' => $estadoNuevo]
        );
    }

    /* Método central de auditoría */

    private static function registrar(
        Model $modelo,
        string $accion,
        ?array $valoresAnteriores,
        ?array $valoresNuevos
    ): void {
        if (! $modelo->exists) {
            throw new \LogicException(
                'El registro debe existir antes de generar su auditoría.'
            );
        }

        Auditoria::create([
            'modelo' => $modelo::class,
            'registro_id' => $modelo->getKey(),
            'accion' => $accion,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $valoresNuevos,
            'usuario_id' => Auth::id(),
        ]);
    }
}
