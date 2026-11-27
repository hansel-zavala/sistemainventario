<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Marca;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarEquipo
{
    private const NOMBRES = [
        'categoria_id' => 'categoría',
        'tipo_equipo_id' => 'tipo de equipo',
        'departamento_id' => 'departamento',
        'marca_id' => 'marca',
        'modelo' => 'modelo',
        'color' => 'color',
        'numero_serie' => 'número de serie',
        'numero_inventario' => 'número de inventario',
        'observaciones' => 'observaciones',
    ];

    private const CAMPOS_TALLER = [
        'categoria_id' => 'categoria_alta_id',
        'tipo_equipo_id' => 'tipo_equipo',
        'marca_id' => 'marca',
    ];

    public static function reglas(bool $alta = true): array
    {
        $presencia = $alta ? 'required' : 'nullable';

        return [
            'categoria_id' => ['bail', 'required', 'integer', 'exists:categorias,id'],
            'tipo_equipo_id' => ['bail', 'required', 'integer', 'exists:tipos_equipo,id'],
            'departamento_id' => ['bail', 'required', 'integer', 'exists:departamentos,id'],
            'marca_id' => ['bail', 'required', 'integer', 'exists:marcas,id'],
            'modelo' => [$presencia, 'string', 'max:100'],
            'color' => [$presencia, 'string', 'max:50'],
            'numero_serie' => [$presencia, 'string', 'max:100'],
            'numero_inventario' => ['required', 'string', 'max:100'],
            'observaciones' => [$presencia, 'string', 'max:2000'],
        ];
    }

    public function categoriasActivas(): Builder
    {
        return Categoria::query()->where('aplica_a', 'equipo')->where('activo', true);
    }

    /** @param array<string, mixed> $datos */
    public function crearDesdeTaller(array $datos, int $usuarioId, string $grupo): Equipo
    {
        $atributos = [
            ...Arr::only($datos, array_keys(self::NOMBRES)),
            'categoria_id' => $datos['categoria_alta_id'] ?? null,
            'tipo_equipo_id' => $this->idPorNombre(TipoEquipo::class, $datos['tipo_equipo'] ?? null),
            'marca_id' => $this->idPorNombre(Marca::class, $datos['marca'] ?? null),
        ];

        try {
            $validados = Validator::make(self::normalizar($atributos), self::reglas(), [
                'required' => 'Completa el campo :attribute para dar de alta el equipo.',
                'max' => 'El campo :attribute admite hasta :max caracteres.',
                'exists' => 'Selecciona una opción válida para :attribute.',
                'integer' => 'Selecciona una opción válida para :attribute.',
                'string' => 'Escribe un valor válido para :attribute.',
            ], self::NOMBRES)->validate();

            return $this->crear($validados, $usuarioId);
        } catch (ValidationException $exception) {
            $campos = [];
            foreach (array_keys($exception->errors()) as $campo) {
                $campos[$campo] = $grupo.'.'.(self::CAMPOS_TALLER[$campo] ?? $campo);
            }

            throw self::adaptarErrores($exception, $campos);
        }
    }

    /** @param array<string, mixed> $datos Validated creation data. */
    public function crear(array $datos, int $usuarioId): Equipo
    {
        $datos = self::normalizar($datos);

        return DB::transaction(function () use ($datos, $usuarioId): Equipo {
            $categoria = $this->categoriasActivas()->whereKey($datos['categoria_id'])->sharedLock()->first();
            if ($categoria === null) {
                throw ValidationException::withMessages(['categoria_id' => 'Seleccione una categoría activa que corresponda a este inventario.']);
            }

            $catalogos = [
                'marca_id' => [Marca::class, 'Seleccione una marca activa vinculada a la categoría.'],
                'tipo_equipo_id' => [TipoEquipo::class, 'Seleccione un tipo de equipo activo vinculado a la categoría.'],
            ];

            foreach ($catalogos as $campo => [$modelo, $mensaje]) {
                $opcion = $modelo::query()->whereKey($datos[$campo])->where('activo', true)->sharedLock()->first();
                if ($opcion === null || $opcion->categorias()->whereKey($categoria->id)->sharedLock()->first() === null) {
                    throw ValidationException::withMessages([$campo => $mensaje]);
                }
            }

            $this->validarIdentificadores($datos['numero_inventario'], $datos['numero_serie']);

            try {
                $equipo = Equipo::create([...$datos, 'creado_por' => $usuarioId]);
            } catch (UniqueConstraintViolationException $exception) {
                $detalle = $exception->getPrevious()?->getMessage() ?? '';

                foreach (['numero_inventario', 'numero_serie'] as $campo) {
                    if (Str::contains($detalle, ['equipos.'.$campo, 'equipos_'.$campo.'_unique'])) {
                        throw $this->errorDuplicado($campo);
                    }
                }

                throw $exception;
            }

            RegistrarAuditoria::creacion($equipo, array_keys(self::NOMBRES));

            return $equipo;
        }, 3);
    }

    public function validarIdentificadores(string $numeroInventario, ?string $numeroSerie, ?int $equipoId = null): void
    {
        foreach (['numero_inventario' => $numeroInventario, 'numero_serie' => $numeroSerie] as $campo => $valor) {
            if ($valor !== null && $this->buscarIdentificador($campo, $valor)->when($equipoId !== null, fn (Builder $query) => $query->whereKeyNot($equipoId))->exists()) {
                throw $this->errorDuplicado($campo);
            }
        }
    }

    public function buscarNumeroInventario(string $numero): Builder
    {
        return $this->buscarIdentificador('numero_inventario', $numero);
    }

    /** @param array<string, string> $campos */
    public static function adaptarErrores(ValidationException $exception, array $campos): ValidationException
    {
        $errores = [];
        foreach ($exception->errors() as $campo => $mensajes) {
            $errores[$campos[$campo] ?? $campo] = $mensajes;
        }

        return ValidationException::withMessages($errores);
    }

    /** @param array<string, mixed> $datos */
    public static function normalizar(array $datos): array
    {
        return array_map(function (mixed $valor): mixed {
            if (! is_string($valor)) {
                return $valor;
            }

            $valor = trim($valor);

            return $valor === '' ? null : $valor;
        }, $datos);
    }

    /** @param class-string<Marca|TipoEquipo> $modelo */
    private function idPorNombre(string $modelo, mixed $nombre): ?int
    {
        return is_string($nombre) && filled($nombre)
            ? $modelo::query()->where('nombre', trim($nombre))->value('id')
            : null;
    }

    private function buscarIdentificador(string $campo, string $valor): Builder
    {
        return Equipo::query()->whereRaw("LOWER(TRIM({$campo})) = ?", [mb_strtolower(trim($valor))]);
    }

    private function errorDuplicado(string $campo): ValidationException
    {
        return ValidationException::withMessages([$campo => 'Ya existe un equipo con este '.self::NOMBRES[$campo].'.']);
    }
}
