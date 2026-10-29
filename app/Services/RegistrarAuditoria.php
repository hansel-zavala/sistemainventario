<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    /* Registrar cambio de rol */

    public static function cambioRol(
        Model $modelo,
        string $rolAnterior,
        string $rolNuevo
    ): void {
        if ($rolAnterior === $rolNuevo) {
            return;
        }

        self::registrar(
            $modelo,
            'cambio_rol',
            ['rol' => $rolAnterior],
            ['rol' => $rolNuevo]
        );
    }

    /* Registrar cambio de contraseña sin exponer datos sensibles */

    public static function cambioContrasena(
        Model $modelo
    ): void {
        self::registrar(
            $modelo,
            'cambio_contrasena',
            null,
            ['contrasena_modificada' => true]
        );
    }

    /**
     * Registra un evento de autenticación sin almacenar credenciales, códigos o tokens.
     *
     * @param  array<string, bool|string>  $detalles
     */
    public static function eventoAutenticacion(
        string $accion,
        ?User $usuarioAfectado,
        Request $request,
        array $detalles = [],
        ?int $usuarioResponsableId = null,
    ): void {
        $detalles = array_filter(
            $detalles,
            fn (string $campo): bool => ! Str::contains(
                Str::lower(Str::ascii($campo)),
                ['password', 'contrasena', 'codigo', 'code', 'token', 'cookie', 'session', 'sesion', 'email', 'correo'],
            ),
            ARRAY_FILTER_USE_KEY,
        );

        Auditoria::create([
            'modelo' => 'autenticacion',
            'registro_id' => $usuarioAfectado?->getKey(),
            'accion' => $accion,
            'valores_anteriores' => null,
            'valores_nuevos' => $detalles === [] ? null : $detalles,
            'usuario_id' => $usuarioResponsableId,
            'ip_address' => $request->ip(),
        ]);
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
            'ip_address' => request()->ip(),
        ]);
    }
}
