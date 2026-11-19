<?php

namespace App\Livewire\Inventario;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CatalogosPorCategoria
{
    public bool $vincularExistente = false;

    public string $catalogoExistenteId = '';

    public function updatedVincularExistente(): void
    {
        $this->resetValidation(['nuevoNombreCatalogo', 'catalogoExistenteId']);
    }

    /** @return class-string<Marca|TipoEquipo>|null */
    protected function modeloCatalogoVinculable(): ?string
    {
        return match ($this->tipoCatalogo) {
            'marca' => Marca::class,
            'tipo_equipo' => self::TIPO_CATEGORIA === 'equipo' ? TipoEquipo::class : null,
            default => null,
        };
    }

    /** @return Collection<int, Marca|TipoEquipo> */
    protected function opcionesCatalogoExistente(?Categoria $categoria): Collection
    {
        $modelo = $this->modeloCatalogoVinculable();

        if (! $this->mostrarModalCatalogo || ! $this->vincularExistente || $categoria === null || $modelo === null) {
            return new Collection;
        }

        return $modelo::query()->where('activo', true)->orderBy('nombre')->orderBy('id')->get(['id', 'nombre']);
    }

    protected function vincularCatalogoRapido(): void
    {
        $modelo = $this->modeloCatalogoVinculable();
        abort_unless($this->mostrarModal && $this->mostrarModalCatalogo && $modelo !== null, 422);

        $this->validate(
            ['catalogoExistenteId' => ['required', 'integer', 'min:1']],
            ['catalogoExistenteId.*' => 'Seleccione una opción del catálogo.'],
        );

        $opcionId = DB::transaction(function () use ($modelo): string {
            $categoria = $this->categoriaActiva(bloquear: true) ?? throw ValidationException::withMessages([
                'catalogoExistenteId' => 'Seleccione una categoría activa en el formulario de inventario.',
            ]);

            $opcion = $modelo::query()->whereKey($this->catalogoExistenteId)
                ->where('activo', true)->lockForUpdate()->first();

            if ($opcion === null) {
                throw ValidationException::withMessages([
                    'catalogoExistenteId' => 'La opción seleccionada ya no está disponible.',
                ]);
            }

            $opcion->categorias()->syncWithoutDetaching([$categoria->id]);

            return (string) $opcion->id;
        }, 3);

        if ($modelo === Marca::class) {
            $this->marcaId = $opcionId;
        } else {
            $this->tipoEquipoId = $opcionId;
        }

        $this->cerrarModalCatalogo();

        session()->flash('mensajeCatalogo', 'Opción vinculada y seleccionada correctamente.');
    }

    protected function categoriaActiva(bool $bloquear = false): ?Categoria
    {
        if (filter_var($this->categoriaId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return null;
        }

        $consulta = Categoria::query()
            ->whereKey($this->categoriaId)
            ->where('aplica_a', self::TIPO_CATEGORIA)
            ->where('activo', true);

        if ($bloquear) {
            $consulta->sharedLock();
        }

        return $consulta->first();
    }

    protected function exigirCategoriaParaAltaRapida(): Categoria
    {
        return $this->categoriaActiva(bloquear: true) ?? throw ValidationException::withMessages([
            'nuevoNombreCatalogo' => 'Seleccione una categoría activa en el formulario de inventario.',
        ]);
    }

    protected function categoriasDelFormulario(Equipo|Herramienta|Insumo|null $registro): Collection
    {
        $consulta = Categoria::query()
            ->where(fn (Builder $query) => $query
                ->where('activo', true)
                ->where('aplica_a', self::TIPO_CATEGORIA));

        if ($registro !== null && (string) $registro->categoria_id === $this->categoriaId) {
            $consulta->orWhereKey($registro->categoria_id);
        }

        return $consulta->orderBy('nombre')->get();
    }

    /**
     * @template T of Marca|TipoEquipo
     *
     * @param  class-string<T>  $modelo
     * @return Collection<int, T>
     */
    protected function opcionesDelCatalogo(string $modelo, ?Categoria $categoria, bool $conservaGuardada, string $seleccion): Collection
    {
        $consulta = $modelo::query();

        if ($categoria !== null) {
            $consulta->where(fn (Builder $query) => $query->where('activo', true)
                ->whereHas('categorias', fn (Builder $query) => $query->whereKey($categoria->id)));

            if ($conservaGuardada) {
                $consulta->orWhereKey($seleccion);
            }
        } else {
            $consulta->whereKey($conservaGuardada ? $seleccion : []);
        }

        return $consulta->orderBy('nombre')->get();
    }

    protected function conservaOpcion(Equipo|Herramienta|Insumo|null $registro, string $campo, string $seleccion): bool
    {
        return $seleccion !== ''
            && $registro !== null
            && (string) $registro->categoria_id === $this->categoriaId
            && (string) $registro->getAttribute($campo) === $seleccion;
    }

    /** @param  class-string<Marca|TipoEquipo>  $modelo */
    protected function opcionDisponible(string $modelo, ?Categoria $categoria, string $id, bool $bloquear = false): bool
    {
        if ($categoria === null || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        $consulta = $modelo::query()->whereKey($id)->where('activo', true);
        if ($bloquear) {
            $consulta->sharedLock();
        }

        $opcion = $consulta->first();
        if ($opcion === null) {
            return false;
        }

        $categorias = $opcion->categorias()->whereKey($categoria->id);

        return $bloquear
            ? $categorias->sharedLock()->first() !== null
            : $categorias->exists();
    }

    protected function limpiarMarcaPorCategoria(?Categoria $categoria): void
    {
        if ($this->marcaId !== '' && ! $this->opcionDisponible(Marca::class, $categoria, $this->marcaId)) {
            $this->marcaId = '';
        }

        $this->resetValidation('marcaId');
    }

    protected function validarCategoriaYMarca(Equipo|Herramienta|Insumo|null $registro): ?Categoria
    {
        $categoria = $this->categoriaActiva(bloquear: true);

        if ($categoria === null && (string) $registro?->categoria_id !== $this->categoriaId) {
            throw ValidationException::withMessages([
                'categoriaId' => 'Seleccione una categoría activa que corresponda a este inventario.',
            ]);
        }

        if ($this->marcaId !== ''
            && ! $this->conservaOpcion($registro, 'marca_id', $this->marcaId)
            && ! $this->opcionDisponible(Marca::class, $categoria, $this->marcaId, bloquear: true)) {
            throw ValidationException::withMessages([
                'marcaId' => 'Seleccione una marca activa vinculada a la categoría.',
            ]);
        }

        return $categoria;
    }
}
