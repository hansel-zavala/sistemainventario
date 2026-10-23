<?php

namespace App\Livewire\Catalogos\TiposEquipo;

use App\Models\TipoEquipo;
use App\Services\RegistrarAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $tipoEquipoId = null;

    public string $nombre = '';

    public bool $activoTipoEquipo = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearTipoEquipo(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarTipoEquipo(int $id): void
    {
        $tipoEquipo = TipoEquipo::findOrFail($id);

        $this->tipoEquipoId = $tipoEquipo->id;
        $this->nombre = $tipoEquipo->nombre;
        $this->activoTipoEquipo = $tipoEquipo->activo;

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
        $this->tipoEquipoId = null;
        $this->nombre = '';
        $this->activoTipoEquipo = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'activoTipoEquipo' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
            'El nombre del tipo de equipo es obligatorio.',

            'nombre.max' =>
            'El nombre no puede superar los 100 caracteres.',
        ];
    }



    public function guardarTipoEquipo(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);

        $esCreacion = $this->tipoEquipoId === null;

        $guardado = DB::transaction(function () use (
            $nombreNormalizado,
            $esCreacion
        ) {

            /* Obtener y bloquear el registro durante la edición */

            $tipoEquipo = null;

            if (! $esCreacion) {
                $tipoEquipo = TipoEquipo::query()
                    ->whereKey($this->tipoEquipoId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar nombres duplicados */

            $duplicado = TipoEquipo::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombreNormalizado)]
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->tipoEquipoId
                    )
                )
                ->exists();

            if ($duplicado) {
                return false;
            }

            /* Preparar los datos */

            $datos = [
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoTipoEquipo,
            ];

            /* Crear tipo de equipo */


            if ($esCreacion) {
                $datos['creado_por'] = Auth::id();
                $tipoEquipo = TipoEquipo::create($datos);
                RegistrarAuditoria::creacion($tipoEquipo, [
                    'nombre',
                    'activo',
                ]);
            } else {
                $tipoEquipo->fill($datos);
                RegistrarAuditoria::guardarActualizacion($tipoEquipo);
            }
            return true;
        }, 3);

        /* Mostrar error cuando exista un duplicado */

        if (! $guardado) {
            $this->addError(
                'nombre',
                'Ya existe un tipo de equipo con este nombre.'
            );

            return;
        }

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Tipo de equipo creado correctamente.'
                : 'Tipo de equipo actualizado correctamente.'
        );

        $this->cerrarModal();

        $this->resetPage();
    }





    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            /* Obtener y bloquear el registro. */

            $tipoEquipo = TipoEquipo::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $tipoEquipo->activo;
            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar estado. */

            $tipoEquipo->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar auditoría mediante el servicio. */

            RegistrarAuditoria::cambioEstado(
                $tipoEquipo,
                $estadoAnterior,
                $nuevoEstado
            );

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Tipo de equipo activado correctamente.'
                : 'Tipo de equipo desactivado correctamente.'
        );
    }




    public function render()
    {
        $tiposEquipo = TipoEquipo::query()
            ->with('creador')

            ->when(
                $this->buscar,
                function ($query) {
                    $query->where(
                        'nombre',
                        'like',
                        '%' . trim($this->buscar) . '%'
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

        return view(
            'livewire.catalogos.tipos-equipo.index',
            compact('tiposEquipo')
        );
    }
}
