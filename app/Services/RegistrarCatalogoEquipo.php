<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Marca;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarCatalogoEquipo
{
    private const MODELOS = [
        'categoria' => Categoria::class,
        'tipo_equipo' => TipoEquipo::class,
        'marca' => Marca::class,
        'departamento' => Departamento::class,
    ];

    private const ETIQUETAS = [
        'categoria' => 'una categoría',
        'tipo_equipo' => 'un tipo de equipo',
        'marca' => 'una marca',
        'departamento' => 'un departamento',
    ];

    public static function permite(string $tipo): bool
    {
        return isset(self::MODELOS[$tipo]);
    }

    public static function esVinculable(string $tipo): bool
    {
        return in_array($tipo, ['tipo_equipo', 'marca'], true);
    }

    public function reglas(string $tipo, bool $vincular): array
    {
        if ($vincular) {
            return ['catalogoExistenteId' => ['required', 'integer', 'min:1']];
        }

        $reglas = ['nuevoNombreCatalogo' => ['required', 'string', 'max:'.($tipo === 'departamento' ? 150 : 100)]];
        if ($tipo === 'departamento') {
            $reglas['nuevaSiglaDepartamento'] = ['nullable', 'string', 'max:20'];
        }

        return $reglas;
    }

    public function mensajes(): array
    {
        return [
            'nuevoNombreCatalogo.required' => 'El nombre es obligatorio.',
            'nuevoNombreCatalogo.max' => 'El nombre no puede superar los :max caracteres.',
            'nuevaSiglaDepartamento.max' => 'La sigla no puede superar los 20 caracteres.',
            'catalogoExistenteId.*' => 'Seleccione una opción del catálogo.',
        ];
    }

    public function categoriaActiva(mixed $id, bool $bloquear = false): ?Categoria
    {
        $id = $this->idValido($id);

        if ($id === null) {
            return null;
        }

        return Categoria::query()->whereKey($id)->where('aplica_a', 'equipo')->where('activo', true)
            ->when($bloquear, fn (Builder $query) => $query->sharedLock())->first();
    }

    /** @return Collection<int, Marca|TipoEquipo> */
    public function disponiblesParaVincular(string $tipo): Collection
    {
        abort_unless(self::esVinculable($tipo), 422);
        $modelo = self::MODELOS[$tipo];

        return $modelo::query()->where('activo', true)->orderBy('nombre')->orderBy('id')->get(['id', 'nombre']);
    }

    /** @return array{tipo_equipo: list<string>, marca: list<string>} */
    public function opciones(mixed $categoriaId): array
    {
        $categoriaId = $this->idValido($categoriaId);
        $opciones = ['tipo_equipo' => [], 'marca' => []];

        if ($categoriaId === null) {
            return $opciones;
        }

        foreach (array_keys($opciones) as $tipo) {
            $modelo = self::MODELOS[$tipo];
            $opciones[$tipo] = $modelo::query()->where('activo', true)
                ->whereHas('categorias', fn (Builder $query) => $query->whereKey($categoriaId)
                    ->where('categorias.activo', true)->where('categorias.aplica_a', 'equipo'))
                ->orderBy('nombre')->pluck('nombre')->all();
        }

        return $opciones;
    }

    public function crear(string $tipo, string $nombre, string $sigla, mixed $categoriaId, int $usuarioId): Categoria|Departamento|Marca|TipoEquipo
    {
        abort_unless(self::permite($tipo), 422);
        $nombre = trim($nombre);
        $modelo = self::MODELOS[$tipo];

        try {
            return DB::transaction(function () use ($tipo, $nombre, $sigla, $categoriaId, $usuarioId, $modelo): Categoria|Departamento|Marca|TipoEquipo {
                $categoria = self::esVinculable($tipo) ? $this->exigirCategoria($categoriaId, 'nuevoNombreCatalogo') : null;
                $duplicado = $modelo::query()->whereRaw('LOWER(TRIM(nombre)) = ?', [Str::lower($nombre)])
                    ->when($tipo === 'categoria', fn (Builder $query) => $query->where('aplica_a', 'equipo'))->exists();
                if ($duplicado) {
                    throw $this->duplicado($tipo);
                }

                $datos = ['nombre' => $nombre, 'activo' => true];
                if ($tipo === 'categoria') {
                    $datos['aplica_a'] = 'equipo';
                }
                if ($tipo === 'departamento') {
                    $datos['sigla'] = filled(trim($sigla)) ? Str::upper(trim($sigla)) : null;
                }

                $opcion = $modelo::create([...$datos, 'creado_por' => $usuarioId]);
                if ($categoria !== null && ($opcion instanceof TipoEquipo || $opcion instanceof Marca)) {
                    $opcion->categorias()->attach($categoria->id);
                }

                RegistrarAuditoria::creacion($opcion, array_keys($datos));

                return $opcion;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicado($tipo);
        }
    }

    public function vincular(string $tipo, int $opcionId, mixed $categoriaId): Marca|TipoEquipo
    {
        abort_unless(self::esVinculable($tipo), 422);
        $modelo = self::MODELOS[$tipo];

        return DB::transaction(function () use ($modelo, $opcionId, $categoriaId): Marca|TipoEquipo {
            $categoria = $this->exigirCategoria($categoriaId, 'catalogoExistenteId');
            $opcion = $modelo::query()->whereKey($opcionId)->where('activo', true)->lockForUpdate()->first();
            if ($opcion === null) {
                throw ValidationException::withMessages(['catalogoExistenteId' => 'La opción seleccionada ya no está disponible.']);
            }

            $opcion->categorias()->syncWithoutDetaching([$categoria->id]);

            return $opcion;
        }, 3);
    }

    /** @param array<string, mixed> $datos */
    public function validarClasificacionTaller(array $datos, bool $completa): ?Categoria
    {
        $categoriaId = $datos['categoria_alta_id'] ?? null;
        if (! $completa && blank($categoriaId)) {
            return null;
        }

        $categoria = $this->categoriaActiva($categoriaId, bloquear: true);
        if ($categoria === null) {
            throw ValidationException::withMessages(['datos.categoria_alta_id' => 'Selecciona una categoría activa de equipos.']);
        }

        $errores = [];
        foreach (['marca' => 'una marca', 'tipo_equipo' => 'un tipo de equipo'] as $tipo => $etiqueta) {
            $nombre = $datos[$tipo] ?? null;
            if (! $completa && blank($nombre)) {
                continue;
            }

            $modelo = self::MODELOS[$tipo];
            $opcion = filled($nombre) ? $modelo::query()->where('nombre', $nombre)->where('activo', true)->sharedLock()->first() : null;
            if ($opcion === null || $opcion->nombre !== $nombre || $opcion->categorias()->whereKey($categoria->id)->sharedLock()->first() === null) {
                $errores['datos.'.$tipo] = 'Selecciona '.$etiqueta.' del catálogo de esta categoría.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $categoria;
    }

    private function exigirCategoria(mixed $categoriaId, string $campo): Categoria
    {
        return $this->categoriaActiva($categoriaId, bloquear: true) ?? throw ValidationException::withMessages([
            $campo => 'Seleccione una categoría activa en el formulario.',
        ]);
    }

    private function duplicado(string $tipo): ValidationException
    {
        $mensaje = 'Ya existe '.self::ETIQUETAS[$tipo].' con este nombre.';
        if (self::esVinculable($tipo)) {
            $mensaje .= ' Elija «Vincular existente» para usarlo.';
        }

        return ValidationException::withMessages(['nuevoNombreCatalogo' => $mensaje]);
    }

    private function idValido(mixed $id): ?int
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }
}
