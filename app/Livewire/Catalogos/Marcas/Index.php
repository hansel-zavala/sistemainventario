<?php

namespace App\Livewire\Catalogos\Marcas;

use App\Models\Marca;
use App\Models\Auditoria;
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

    public ?int $marcaId = null;

    public string $nombre = '';

    public bool $activoMarca = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearMarca(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarMarca(int $id): void
    {
        $marca = Marca::findOrFail($id);

        $this->marcaId = $marca->id;
        $this->nombre = $marca->nombre;
        $this->activoMarca = $marca->activo;

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
        $this->marcaId = null;
        $this->nombre = '';
        $this->activoMarca = true;
    }


    protected function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],

            'activoMarca' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombre.required' =>
            'El nombre de la marca es obligatorio.',

            'nombre.max' =>
            'El nombre no puede superar los 100 caracteres.',
        ];
    }



    public function guardarMarca(): void
    {
        $this->validate();

        $nombreNormalizado = trim($this->nombre);
        $esCreacion = $this->marcaId === null;

        $guardado = DB::transaction(function () use (
            $nombreNormalizado,
            $esCreacion
        ) {

            /* Bloquear la marca cuando estamos editando */

            $marca = null;

            if (! $esCreacion) {
                $marca = Marca::query()
                    ->whereKey($this->marcaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar que no exista otra marca con el mismo nombre */

            $duplicada = Marca::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre)) = ?',
                    [mb_strtolower($nombreNormalizado)]
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->marcaId
                    )
                )
                ->exists();

            if ($duplicada) {
                return false;
            }

            $datos = [
                'nombre' => $nombreNormalizado,
                'activo' => $this->activoMarca,
            ];

            /* Crear marca */

            if ($esCreacion) {

                $datos['creado_por'] = Auth::id();

                $marca = Marca::create($datos);

                Auditoria::create([
                    'modelo' => Marca::class,
                    'registro_id' => $marca->id,
                    'accion' => 'creacion',
                    'valores_anteriores' => null,
                    'valores_nuevos' => $marca->only([
                        'nombre',
                        'activo',
                    ]),
                    'usuario_id' => Auth::id(),
                ]);
            } else {

                /* Detectar y guardar solamente los cambios reales */

                $marca->fill($datos);

                $valoresNuevos = $marca->getDirty();

                if (! empty($valoresNuevos)) {

                    $valoresAnteriores = [];

                    foreach ($valoresNuevos as $campo => $valor) {
                        $valoresAnteriores[$campo] =
                            $marca->getOriginal($campo);
                    }

                    $marca->save();

                    Auditoria::create([
                        'modelo' => Marca::class,
                        'registro_id' => $marca->id,
                        'accion' => 'actualizacion',
                        'valores_anteriores' => $valoresAnteriores,
                        'valores_nuevos' => $valoresNuevos,
                        'usuario_id' => Auth::id(),
                    ]);
                }
            }

            return true;
        }, 3);

        /* Mostrar error si el nombre está duplicado */

        if (! $guardado) {
            $this->addError(
                'nombre',
                'Ya existe una marca con este nombre.'
            );

            return;
        }

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Marca creada correctamente.'
                : 'Marca actualizada correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }




    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            $marca = Marca::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $marca->activo;
            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar el estado de la marca. */
            $marca->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar la operación en auditoría. */
            Auditoria::create([
                'modelo' => Marca::class,
                'registro_id' => $marca->id,
                'accion' => $nuevoEstado
                    ? 'activacion'
                    : 'desactivacion',
                'valores_anteriores' => [
                    'activo' => $estadoAnterior,
                ],
                'valores_nuevos' => [
                    'activo' => $nuevoEstado,
                ],
                'usuario_id' => Auth::id(),
            ]);

            return $nuevoEstado;
        }, 3);

        session()->flash(
            'mensaje',
            $nuevoEstado
                ? 'Marca activada correctamente.'
                : 'Marca desactivada correctamente.'
        );
    }



    public function render()
    {
        $marcas = Marca::query()
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
            'livewire.catalogos.marcas.index',
            compact('marcas')
        );
    }
}
