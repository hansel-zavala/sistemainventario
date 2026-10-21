<?php

namespace App\Livewire\Catalogos\UnidadesMedida;

use App\Models\UnidadMedida;
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

    public ?int $unidadMedidaId = null;

    public string $nombreSingular = '';

    public string $nombrePlural = '';

    public string $abreviatura = '';

    public bool $permiteDecimales = false;

    public bool $activoUnidad = true;


    public function updatingBuscar(): void
    {
        $this->resetPage();
    }


    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }


    public function crearUnidadMedida(): void
    {
        $this->resetFormulario();

        $this->mostrarModal = true;
    }


    public function editarUnidadMedida(int $id): void
    {
        $unidad = UnidadMedida::findOrFail($id);

        $this->unidadMedidaId = $unidad->id;
        $this->nombreSingular = $unidad->nombre_singular;
        $this->nombrePlural = $unidad->nombre_plural;
        $this->abreviatura = $unidad->abreviatura ?? '';
        $this->permiteDecimales = $unidad->permite_decimales;
        $this->activoUnidad = $unidad->activo;

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
        $this->unidadMedidaId = null;
        $this->nombreSingular = '';
        $this->nombrePlural = '';
        $this->abreviatura = '';
        $this->permiteDecimales = false;
        $this->activoUnidad = true;
    }


    protected function rules(): array
    {
        return [
            'nombreSingular' => [
                'required',
                'string',
                'max:50',
            ],

            'nombrePlural' => [
                'required',
                'string',
                'max:50',
            ],

            'abreviatura' => [
                'nullable',
                'string',
                'max:15',
            ],

            'permiteDecimales' => [
                'boolean',
            ],

            'activoUnidad' => [
                'boolean',
            ],
        ];
    }


    protected function messages(): array
    {
        return [
            'nombreSingular.required' =>
            'El nombre en singular es obligatorio.',

            'nombreSingular.max' =>
            'El nombre en singular no puede superar los 50 caracteres.',

            'nombrePlural.required' =>
            'El nombre en plural es obligatorio.',

            'nombrePlural.max' =>
            'El nombre en plural no puede superar los 50 caracteres.',

            'abreviatura.max' =>
            'La abreviatura no puede superar los 15 caracteres.',
        ];
    }



    public function guardarUnidadMedida(): void
    {
        $this->validate();

        $singular = trim($this->nombreSingular);
        $plural = trim($this->nombrePlural);
        $abreviatura = trim($this->abreviatura);

        $esCreacion = $this->unidadMedidaId === null;

        $resultado = DB::transaction(function () use (
            $singular,
            $plural,
            $abreviatura,
            $esCreacion
        ) {

            $unidad = null;

            /* Bloquear el registro en caso de edición. */
            if (! $esCreacion) {
                $unidad = UnidadMedida::query()
                    ->whereKey($this->unidadMedidaId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            /* Validar nombre singular duplicado. */
            $duplicadoSingular = UnidadMedida::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre_singular)) = ?',
                    [mb_strtolower($singular)]
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->unidadMedidaId
                    )
                )
                ->exists();

            if ($duplicadoSingular) {
                return 'singular';
            }

            /* Validar nombre plural duplicado. */
            $duplicadoPlural = UnidadMedida::query()
                ->whereRaw(
                    'LOWER(TRIM(nombre_plural)) = ?',
                    [mb_strtolower($plural)]
                )
                ->when(
                    ! $esCreacion,
                    fn($query) => $query->where(
                        'id',
                        '!=',
                        $this->unidadMedidaId
                    )
                )
                ->exists();

            if ($duplicadoPlural) {
                return 'plural';
            }

            /* Datos generales de la unidad. */
            $datos = [
                'nombre_singular' => $singular,
                'nombre_plural' => $plural,

                'abreviatura' => $abreviatura !== ''
                    ? $abreviatura
                    : null,

                'permite_decimales' => $this->permiteDecimales,
                'activo' => $this->activoUnidad,
            ];

            /* Creación de una unidad. */
            if ($esCreacion) {

                $datos['creado_por'] = Auth::id();

                $unidad = UnidadMedida::create($datos);

                Auditoria::create([
                    'modelo' => UnidadMedida::class,
                    'registro_id' => $unidad->id,
                    'accion' => 'creacion',
                    'valores_anteriores' => null,
                    'valores_nuevos' => $unidad->only([
                        'nombre_singular',
                        'nombre_plural',
                        'abreviatura',
                        'permite_decimales',
                        'activo',
                    ]),
                    'usuario_id' => Auth::id(),
                ]);
            } else {

                /* Detectar exclusivamente los cambios reales. */
                $unidad->fill($datos);

                $valoresNuevos = $unidad->getDirty();

                if (! empty($valoresNuevos)) {

                    $valoresAnteriores = [];

                    foreach ($valoresNuevos as $campo => $valor) {
                        $valoresAnteriores[$campo] =
                            $unidad->getOriginal($campo);
                    }

                    /* Guardar datos actualizados. */
                    $unidad->save();

                    /* Guardar auditoría en la misma transacción. */
                    Auditoria::create([
                        'modelo' => UnidadMedida::class,
                        'registro_id' => $unidad->id,
                        'accion' => 'actualizacion',
                        'valores_anteriores' => $valoresAnteriores,
                        'valores_nuevos' => $valoresNuevos,
                        'usuario_id' => Auth::id(),
                    ]);
                }
            }

            return 'ok';
        }, 3);

        /* Mostrar errores de duplicados. */
        if ($resultado === 'singular') {
            $this->addError(
                'nombreSingular',
                'Ya existe una unidad de medida con este nombre en singular.'
            );

            return;
        }

        if ($resultado === 'plural') {
            $this->addError(
                'nombrePlural',
                'Ya existe una unidad de medida con este nombre en plural.'
            );

            return;
        }

        session()->flash(
            'mensaje',
            $esCreacion
                ? 'Unidad de medida creada correctamente.'
                : 'Unidad de medida actualizada correctamente.'
        );

        $this->cerrarModal();
        $this->resetPage();
    }




    public function cambiarEstado(int $id): void
    {
        $nuevoEstado = DB::transaction(function () use ($id) {

            $unidad = UnidadMedida::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = (bool) $unidad->activo;
            $nuevoEstado = ! $estadoAnterior;

            /* Actualizar el estado. */
            $unidad->update([
                'activo' => $nuevoEstado,
            ]);

            /* Registrar la auditoría del cambio. */
            Auditoria::create([
                'modelo' => UnidadMedida::class,
                'registro_id' => $unidad->id,
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
                ? 'Unidad de medida activada correctamente.'
                : 'Unidad de medida desactivada correctamente.'
        );
    }



    public function render()
    {
        $unidades = UnidadMedida::query()
            ->with('creador')

            ->when(
                $this->buscar,
                function ($query) {
                    $query->where(function ($subQuery) {
                        $subQuery
                            ->where(
                                'nombre_singular',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            )
                            ->orWhere(
                                'nombre_plural',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            )
                            ->orWhere(
                                'abreviatura',
                                'like',
                                '%' . trim($this->buscar) . '%'
                            );
                    });
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

            ->orderBy('nombre_singular')
            ->paginate(10);

        return view(
            'livewire.catalogos.unidades-medida.index',
            compact('unidades')
        );
    }
}
