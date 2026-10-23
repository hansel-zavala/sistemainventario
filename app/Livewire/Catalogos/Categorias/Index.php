<?php

namespace App\Livewire\Catalogos\Categorias;

use App\Models\Categoria;
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

    public string $filtroTipo = '';

    public string $filtroEstado = '';

    public bool $mostrarModal = false;

    public ?int $categoriaId = null;

    public string $nombre = '';

    public string $aplicaA = 'equipo';

    public bool $activoCategoria = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearCategoria(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarCategoria(int $id): void
    {
        $categoria = Categoria::findOrFail($id);

        $this->categoriaId = $categoria->id;
        $this->nombre = $categoria->nombre;
        $this->aplicaA = $categoria->aplica_a;
        $this->activoCategoria = $categoria->activo;

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
        $this->categoriaId = null;
        $this->nombre = '';
        $this->aplicaA = 'equipo';
        $this->activoCategoria = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'aplicaA' => [
                'required',
                'in:equipo,herramienta,insumo',
            ],

            'activoCategoria' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
            'El nombre de la categoría es obligatorio.',

            'nombre.max' =>
            'El nombre no puede superar los 100 caracteres.',

            'aplicaA.required' =>
            'Debe seleccionar a qué tipo de inventario aplica la categoría.',

            'aplicaA.in' =>
            'El tipo seleccionado no es válido.',
        ];
    }



    public function guardarCategoria(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);

        $esCreacion = $this->categoriaId === null;

        $guardado = DB::transaction(function () use (
            $nombreNormalizado,
            $esCreacion
        ) {

            /* Obtener la categoría actual para edición */

            $categoria = null;

            if (! $esCreacion) {
                $categoria = Categoria::query()
                    ->whereKey($this->categoriaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar nombre duplicado para el mismo tipo */

            $duplicada = Categoria::query()
                ->where('aplica_a', $this->aplicaA)
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombreNormalizado)]
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->categoriaId
                    )
                )
                ->exists();

            if ($duplicada) {
                return false;
            }

            /* Preparar datos generales */

            $datos = [
                'nombre' => $nombreNormalizado,
                'aplica_a' => $this->aplicaA,
                'activo' => $this->activoCategoria,
            ];

            /* Registrar nueva categoría */


            if ($esCreacion) {

                $datos['creado_por'] = Auth::id();
                
                $categoria = Categoria::create($datos);
                RegistrarAuditoria::creacion($categoria, [
                    'nombre',
                    'aplica_a',
                    'activo',
                ]);
            } else {
                $categoria->fill($datos);
                RegistrarAuditoria::guardarActualizacion($categoria);
            }


            return true;
        }, 3);

        /* Validación y mensajes */

        if (! $guardado) {
            $this->addError(
                'nombre',
                'Ya existe una categoría con este nombre para el tipo seleccionado.'
            );

            return;
        }

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Categoría creada correctamente.'
                : 'Categoría actualizada correctamente.'
        );

        $this->cerrarModal();

        $this->resetPage();
    }





    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            /* Obtener y bloquear la categoría. */
            $categoria = Categoria::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $categoria->activo;
            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar el estado. */
            $categoria->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar el cambio mediante el servicio. */
            RegistrarAuditoria::cambioEstado(
                $categoria,
                $estadoAnterior,
                $nuevoEstado
            );

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Categoría activada correctamente.'
                : 'Categoría desactivada correctamente.'
        );
    }



    public function render()
    {
        $categorias = Categoria::query()
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
                $this->filtroTipo,
                function ($query) {
                    $query->where(
                        'aplica_a',
                        $this->filtroTipo
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
            'livewire.catalogos.categorias.index',
            compact('categorias')
        );
    }
}
