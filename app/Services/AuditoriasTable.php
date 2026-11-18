<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\Tinta;
use App\Models\TipoEquipo;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuditoriasTable
{
    public const COLUMNS = ['fecha' => 'Fecha', 'responsable' => 'Responsable', 'modulo' => 'Módulo', 'registro' => 'Registro', 'accion' => 'Acción'];

    public const MODULOS = [
        'autenticacion' => 'Autenticación',
        User::class => 'Usuarios',
        Equipo::class => 'Equipos tecnológicos',
        Herramienta::class => 'Herramientas',
        Insumo::class => 'Insumos',
        Tinta::class => 'Tinta',
        Categoria::class => 'Categorías',
        Marca::class => 'Marcas',
        TipoEquipo::class => 'Tipos de equipo',
        Departamento::class => 'Departamentos',
        UnidadMedida::class => 'Unidades de medida',
    ];

    public const ACCIONES = [
        'creacion' => 'Creación',
        'actualizacion' => 'Actualización',
        'activacion' => 'Activación',
        'desactivacion' => 'Desactivación',
        'cambio_rol' => 'Cambio de rol',
        'cambio_contrasena' => 'Cambio de contraseña',
        'inicio_sesion_exitoso' => 'Inicio de sesión exitoso',
        'inicio_sesion_fallido' => 'Inicio de sesión fallido',
        'bloqueo_login' => 'Bloqueo de inicio de sesión',
        'cierre_sesion' => 'Cierre de sesión',
        'sesion_vencida' => 'Sesión vencida',
        'recuperacion_solicitada' => 'Recuperación solicitada',
        'codigo_recuperacion_verificado' => 'Código de recuperación verificado',
        'contrasena_restablecida' => 'Contraseña restablecida',
    ];

    public static function filterRules(array $filters): array
    {
        $rules = MovimientosTable::filterRules($filters);
        unset($rules['tipoArticulo']);

        return [
            ...$rules,
            'buscar' => ['nullable', 'string', 'max:200'],
            'filtroModulo' => ['nullable', 'string', Rule::in(array_keys(self::MODULOS))],
            'filtroAccion' => ['nullable', 'string', Rule::in(array_keys(self::ACCIONES))],
            'filtroUsuario' => ['nullable', 'bail', 'integer', 'exists:users,id'],
        ];
    }

    /** @return Builder<Auditoria> */
    public function query(array $filters): Builder
    {
        $query = Auditoria::query()->select('id', 'created_at', 'usuario_id', 'modelo', 'registro_id', 'accion')
            ->with(['usuario:id,name', 'usuarioAfectado:id,name']);
        foreach (['filtroModulo' => 'modelo', 'filtroAccion' => 'accion', 'filtroUsuario' => 'usuario_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filter === 'filtroUsuario' ? (int) $filters[$filter] : $filters[$filter]);
            }
        }
        if (! empty($filters['desde'])) {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }
        if (! empty($filters['hasta'])) {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        $search = trim($filters['buscar'] ?? '');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $normalized = Str::lower(Str::ascii($search));
            $modules = array_keys(array_filter(self::MODULOS, fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $normalized)));
            $actions = array_keys(array_filter(self::ACCIONES, fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $normalized)));

            $query->where(function (Builder $audits) use ($term, $modules, $actions): void {
                $audits->whereHas('usuario', fn (Builder $user) => $user->where('name', 'like', $term))
                    ->orWhere('accion', 'like', $term)
                    ->orWhere('modelo', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhereIn('modelo', $modules)
                    ->orWhereIn('accion', $actions)
                    ->orWhere(fn (Builder $auth) => $auth->where('modelo', 'autenticacion')
                        ->whereHas('usuarioAfectado', fn (Builder $user) => $user->where('name', 'like', $term)));
            });
        }

        $direction = ($filters['orden'] ?? MovimientosTable::DEFAULT_ORDER) === 'antiguos' ? 'asc' : 'desc';
        $query->orderBy('created_at', $direction)->orderBy('id', $direction);

        return $query;
    }

    /** @return array{fecha: string, responsable: string, modulo: string, registro: string, accion: string} */
    public static function values(Auditoria $audit): array
    {
        $authentication = $audit->modelo === 'autenticacion';

        return [
            'fecha' => $audit->created_at?->format('d/m/Y H:i:s') ?? 'Sin fecha',
            'responsable' => $audit->usuario?->name ?? ($authentication ? 'Sistema / No autenticado' : 'Usuario no disponible'),
            'modulo' => self::MODULOS[$audit->modelo] ?? class_basename($audit->modelo),
            'registro' => $authentication ? ($audit->usuarioAfectado?->name ?? 'Evento anónimo') : '#'.$audit->registro_id,
            'accion' => self::ACCIONES[$audit->accion] ?? ucfirst($audit->accion),
        ];
    }

    public static function row(Auditoria $audit, array $columns): array
    {
        $values = self::values($audit);

        return array_map(fn (string $column): string => $values[$column], $columns);
    }
}
