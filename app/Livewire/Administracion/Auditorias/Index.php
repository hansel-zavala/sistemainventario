<?php

namespace App\Livewire\Administracion\Auditorias;

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
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroModulo = '';

    public string $filtroAccion = '';

    public string $filtroUsuario = '';

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public bool $mostrarDetalle = false;

    public ?int $auditoriaSeleccionadaId = null;

    /* Módulos disponibles */
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

    /* Seguridad */
    public function mount(): void
    {
        $this->autorizar();
    }

    /* Reiniciar paginación al cambiar filtros */
    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroModulo(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroAccion(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroUsuario(): void
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

    /* Limpiar filtros */
    public function limpiarFiltros(): void
    {
        $this->reset([
            'buscar',
            'filtroModulo',
            'filtroAccion',
            'filtroUsuario',
            'fechaDesde',
            'fechaHasta',
        ]);

        $this->resetPage();
    }

    /* Consultar detalle de una auditoría */

    public function verDetalle(int $id): void
    {
        $this->autorizar();

        $this->auditoriaSeleccionadaId = Auditoria::query()
            ->findOrFail($id)
            ->id;

        $this->mostrarDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->mostrarDetalle = false;
        $this->auditoriaSeleccionadaId = null;
    }

    private function autorizar(): void
    {
        $usuario = Auth::user();

        abort_unless(
            $usuario instanceof User
                && $usuario->roles()
                    ->where('name', 'Administrador')
                    ->exists(),
            403
        );
    }

    /* Consultar auditorías */

    public function render()
    {
        $this->autorizar();

        $auditorias = Auditoria::query()
            ->with(['usuario:id,name', 'usuarioAfectado:id,name'])

            ->when(
                $this->filtroModulo !== ''
                    && array_key_exists(
                        $this->filtroModulo,
                        self::MODULOS
                    ),
                fn ($query) => $query->where(
                    'modelo',
                    $this->filtroModulo
                )
            )

            ->when(
                $this->filtroAccion !== ''
                    && array_key_exists(
                        $this->filtroAccion,
                        self::ACCIONES
                    ),
                fn ($query) => $query->where(
                    'accion',
                    $this->filtroAccion
                )
            )

            ->when(
                $this->filtroUsuario !== ''
                    && ctype_digit($this->filtroUsuario),
                fn ($query) => $query->where(
                    'usuario_id',
                    (int) $this->filtroUsuario
                )
            )

            ->when(
                $this->fechaDesde !== '',
                fn ($query) => $query->whereDate(
                    'created_at',
                    '>=',
                    $this->fechaDesde
                )
            )

            ->when(
                $this->fechaHasta !== '',
                fn ($query) => $query->whereDate(
                    'created_at',
                    '<=',
                    $this->fechaHasta
                )
            )

            ->when(
                trim($this->buscar) !== '',
                function ($query) {
                    $buscar = '%'.trim($this->buscar).'%';

                    $query->where(function ($subquery) use ($buscar) {
                        $subquery
                            ->whereHas(
                                'usuario',
                                fn ($usuarioQuery) => $usuarioQuery
                                    ->where('name', 'like', $buscar)
                            )
                            ->orWhere(
                                'accion',
                                'like',
                                $buscar
                            )
                            ->orWhere(
                                'modelo',
                                'like',
                                $buscar
                            )
                            ->orWhere(
                                fn ($consultaAutenticacion) => $consultaAutenticacion
                                    ->where('modelo', 'autenticacion')
                                    ->whereHas(
                                        'usuarioAfectado',
                                        fn ($usuarioQuery) => $usuarioQuery->where('name', 'like', $buscar)
                                    )
                            )
                            ->orWhere(
                                'ip_address',
                                'like',
                                $buscar
                            );
                    });
                }
            )

            ->orderByDesc('id')
            ->paginate(15);

        $usuarios = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $auditoriaSeleccionada = null;

        if (
            $this->mostrarDetalle
            && $this->auditoriaSeleccionadaId !== null
        ) {
            $auditoriaSeleccionada = Auditoria::query()
                ->with(['usuario:id,name', 'usuarioAfectado:id,name'])
                ->find($this->auditoriaSeleccionadaId);
        }

        return view(
            'livewire.administracion.auditorias.index',
            [
                'auditorias' => $auditorias,
                'usuarios' => $usuarios,
                'auditoriaSeleccionada' => $auditoriaSeleccionada,
                'modulos' => self::MODULOS,
                'acciones' => self::ACCIONES,
            ]
        );
    }
}
