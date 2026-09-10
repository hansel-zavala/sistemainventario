<?php

namespace App\Livewire\Administracion\Usuarios;

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
                'unique:users,email,' . $this->usuarioId,
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

        if ($this->usuarioId === null) {

            $usuario = User::create([
                'name' => trim($this->name),
                'email' => strtolower(trim($this->email)),
                'password' => $this->password,
                'activo' => $this->activoUsuario,
            ]);

            $usuario->assignRole($this->rolUsuario);

            session()->flash(
                'mensaje',
                'Usuario creado correctamente.'
            );

        } else {

            $usuario = User::findOrFail($this->usuarioId);

            // Evitar que el usuario se desactive a sí mismo
            if (
                $usuario->id === Auth::id()
                && ! $this->activoUsuario
            ) {
                $this->addError(
                    'activoUsuario',
                    'No puede desactivar su propio usuario.'
                );

                return;
            }

            // Evitar dejar el sistema sin Administradores activos
            if ($usuario->hasRole('Administrador')) {

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
                        $this->addError(
                            'rolUsuario',
                            'Debe existir al menos un Administrador activo en el sistema.'
                        );

                        return;
                    }
                }
            }

            // Actualizar datos
            $usuario->name = trim($this->name);
            $usuario->email = strtolower(trim($this->email));
            $usuario->activo = $this->activoUsuario;

            if ($this->password !== '') {
                $usuario->password = $this->password;
            }

            $usuario->save();

            // Actualizar rol
            $usuario->syncRoles([
                $this->rolUsuario,
            ]);

            session()->flash(
                'mensaje',
                'Usuario actualizado correctamente.'
            );
        }

        $this->cerrarModal();

        $this->resetPage();
    }

    public function render()
    {
        $usuarios = User::query()
            ->with('roles')

            ->when($this->buscar, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where(
                            'name',
                            'like',
                            '%' . $this->buscar . '%'
                        )
                        ->orWhere(
                            'email',
                            'like',
                            '%' . $this->buscar . '%'
                        );
                });
            })

            ->when($this->rol, function ($query) {
                $query->role($this->rol);
            })

            ->when($this->estado !== '', function ($query) {
                $query->where(
                    'activo',
                    $this->estado === 'activo'
                );
            })

            ->orderBy('name')
            ->paginate(10);

        return view(
            'livewire.administracion.usuarios.index',
            compact('usuarios')
        );
    }
}