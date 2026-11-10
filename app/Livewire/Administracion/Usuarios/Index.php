<?php

namespace App\Livewire\Administracion\Usuarios;

use App\Models\User;
use App\Services\RegistrarAuditoria;
use App\Services\UsuariosTable;
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

    public const COLUMNS = UsuariosTable::COLUMNS;

    public array $visibleColumns = ['name', 'email', 'rol', 'activo'];

    public string $sortColumn = '';

    public string $sortDirection = '';

    public function setVisibleColumns(array $columns): void
    {
        $columns = array_values(array_intersect(array_keys(self::COLUMNS), array_filter($columns, 'is_string')));
        if ($columns !== []) {
            $this->visibleColumns = $columns;
        }
    }

    public function toggleColumn(string $column): void
    {
        abort_unless(array_key_exists($column, self::COLUMNS), 422);
        $this->setVisibleColumns(in_array($column, $this->visibleColumns, true)
            ? array_diff($this->visibleColumns, [$column])
            : [...$this->visibleColumns, $column]);
    }

    public function sortBy(string $column): void
    {
        abort_unless(array_key_exists($column, self::COLUMNS), 422);
        if ($this->sortColumn !== $column) {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        } elseif ($this->sortDirection === 'asc') {
            $this->sortDirection = 'desc';
        } else {
            $this->sortColumn = '';
            $this->sortDirection = '';
        }
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('buscar', 'rol', 'estado');
        $this->resetPage();
    }

    public string $buscar = '';

    public string $rol = '';

    public string $estado = '';

    public bool $mostrarModal = false;

    public ?int $usuarioId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $rolUsuario = 'Técnico';

    public bool $activoUsuario = true;

    public function crearUsuario(): void
    {
        $this->resetFormulario();

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
        $this->usuarioId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->rolUsuario = 'Técnico';
        $this->activoUsuario = true;
    }

    public function editarUsuario(int $id): void
    {
        $usuario = User::with('roles')->findOrFail($id);

        $this->usuarioId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->rolUsuario = $usuario->getRoleNames()->first() ?? 'Técnico';
        $this->activoUsuario = (bool) $usuario->activo;

        $this->resetValidation();

        $this->mostrarModal = true;
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingRol(): void
    {
        $this->resetPage();
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email,'.$this->usuarioId,
            ],

            'rolUsuario' => [
                'required',
                'in:Administrador,Técnico',
            ],

            'activoUsuario' => [
                'boolean',
            ],
        ];
    }

    protected function passwordRules(): array
    {
        if ($this->usuarioId === null) {
            return [
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ];
        }

        return [
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',

            'rolUsuario.required' => 'Debe seleccionar un rol.',
            'rolUsuario.in' => 'El rol seleccionado no es válido.',
        ];
    }

    public function guardarUsuario(): void
    {
        $this->validate(
            array_merge(
                $this->rules(),
                $this->passwordRules()
            )
        );

        $esCreacion = $this->usuarioId === null;

        DB::transaction(function () use ($esCreacion) {

            /* Crear usuario */

            if ($esCreacion) {

                $usuario = User::create([
                    'name' => trim($this->name),
                    'email' => strtolower(trim($this->email)),
                    'password' => $this->password,
                    'activo' => $this->activoUsuario,
                ]);

                $usuario->assignRole($this->rolUsuario);

                /* Registrar creación sin incluir contraseña. */
                RegistrarAuditoria::creacion($usuario, [
                    'name',
                    'email',
                    'activo',
                ]);

                /* Registrar asignación inicial del rol. */
                RegistrarAuditoria::cambioRol(
                    $usuario,
                    'Sin rol',
                    $this->rolUsuario
                );

                return;
            }

            /* Obtener y bloquear usuario durante la edición */

            $usuario = User::query()
                ->whereKey($this->usuarioId)
                ->lockForUpdate()
                ->firstOrFail();

            $rolAnterior = $usuario->getRoleNames()->first()
                ?? 'Sin rol';

            /* Impedir autodesactivación */

            if (
                $usuario->id === Auth::id()
                && ! $this->activoUsuario
            ) {
                throw ValidationException::withMessages([
                    'activoUsuario' => 'No puede desactivar su propio usuario.',
                ]);
            }

            /* Proteger al último Administrador activo */

            if ($rolAnterior === 'Administrador') {

                $quitaraRolAdministrador =
                    $this->rolUsuario !== 'Administrador';

                $desactivaraAdministrador =
                    ! $this->activoUsuario;

                if (
                    $quitaraRolAdministrador
                    || $desactivaraAdministrador
                ) {
                    $otrosAdministradoresActivos = User::query()
                        ->where('id', '!=', $usuario->id)
                        ->where('activo', true)
                        ->role('Administrador')
                        ->count();

                    if ($otrosAdministradoresActivos === 0) {
                        throw ValidationException::withMessages([
                            'rolUsuario' => 'Debe existir al menos un Administrador activo en el sistema.',
                        ]);
                    }
                }
            }

            /* Actualizar únicamente datos administrativos */

            $usuario->fill([
                'name' => trim($this->name),
                'email' => strtolower(trim($this->email)),
                'activo' => $this->activoUsuario,
            ]);

            RegistrarAuditoria::guardarActualizacion($usuario);

            /* Actualizar rol cuando haya cambiado */

            if ($rolAnterior !== $this->rolUsuario) {

                $usuario->syncRoles([
                    $this->rolUsuario,
                ]);

                RegistrarAuditoria::cambioRol(
                    $usuario,
                    $rolAnterior,
                    $this->rolUsuario
                );
            }

            /* Cambiar contraseña si se proporcionó una nueva */

            if ($this->password !== '') {

                $usuario->password = $this->password;
                $usuario->save();

                RegistrarAuditoria::cambioContrasena($usuario);
            }
        }, 3);

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Usuario creado correctamente.'
                : 'Usuario actualizado correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }

    public function render()
    {
        $usuarios = app(UsuariosTable::class)->query([
            'buscar' => $this->buscar,
            'rol' => $this->rol,
            'estado' => $this->estado,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
        ])->paginate(10);

        return view(
            'livewire.administracion.usuarios.index',
            compact('usuarios')
        );
    }
}
