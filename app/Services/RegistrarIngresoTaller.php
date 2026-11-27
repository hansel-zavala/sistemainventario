<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\EquipoTaller;
use App\Models\IngresoTaller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrarIngresoTaller
{
    public const DISCO_FIRMAS = 'local';

    public function __construct(
        private readonly RegistrarEquipo $equipos,
        private readonly RegistrarCatalogoEquipo $catalogos,
    ) {}

    public function buscarInventario(string $buscar): Builder
    {
        return Equipo::query()->whereLike('numero_inventario', '%'.mb_substr(trim($buscar), 0, 100).'%')
            ->orderBy('numero_inventario')->orderBy('id');
    }

    /** @return array{categoria_alta_id: int, categoria_nombre: string, tipo_equipo: string, numero_inventario: string, numero_serie: ?string, marca: string, modelo: ?string, color: ?string} */
    public function datosInventario(Equipo $equipo): array
    {
        $equipo->loadMissing(['categoria:id,nombre', 'tipoEquipo:id,nombre', 'marca:id,nombre']);

        return [
            'categoria_alta_id' => $equipo->categoria_id,
            'categoria_nombre' => $equipo->categoria->nombre,
            'tipo_equipo' => $equipo->tipoEquipo->nombre,
            'numero_inventario' => $equipo->numero_inventario,
            'numero_serie' => $equipo->numero_serie,
            'marca' => $equipo->marca->nombre,
            'modelo' => $equipo->modelo,
            'color' => $equipo->color,
        ];
    }

    /** @param array<string, string|int|bool|null> $datos */
    public function guardar(
        User $tecnico,
        ?int $ingresoId,
        string $token,
        int $version,
        array $datos,
        ?int $equipoId,
        ?int $equipoTallerId,
        bool $emitir,
    ): IngresoTaller {
        return DB::transaction(function () use ($tecnico, $ingresoId, $token, $version, $datos, $equipoId, $equipoTallerId, $emitir): IngresoTaller {
            if ($ingresoId === null) {
                $ingresoId = IngresoTaller::query()->createOrFirst(['borrador_token' => $token], [
                    'tecnico_id' => $tecnico->id,
                    'tecnico_nombre' => $tecnico->name,
                ])->id;
            }

            $ingreso = IngresoTaller::query()->lockForUpdate()->findOrFail($ingresoId);
            $this->validarEditable($ingreso);

            $reintento = $version !== $ingreso->version;
            $altaSolicitada = $equipoId === null && (bool) ($datos['alta_inventario'] ?? false);
            $reintentoAlta = $emitir && $reintento && $altaSolicitada && $ingreso->emitido()
                && $ingreso->alta_inventario && $ingreso->equipo_id !== null;
            $alta = $altaSolicitada && (! $ingreso->emitido() || $reintentoAlta);

            $datos['alta_inventario'] = $ingreso->emitido() ? $ingreso->alta_inventario : $alta;

            $datos = $this->completarDatos($ingreso, $datos, $equipoId);
            if ($equipoId === null) {
                $identificacionEmitida = $reintentoAlta || ($ingreso->emitido() && $ingreso->equipo_id === null);
                $identificacionSinCambios = $identificacionEmitida && ! (clone $ingreso)->fill($datos)->isDirty(IngresoTaller::DATOS_EQUIPO);
                $datos['categoria_nombre'] = $identificacionSinCambios
                    ? $ingreso->categoria_nombre
                    : $this->catalogos->validarClasificacionTaller($datos, completa: $emitir)?->nombre;
            }

            $numeroInventario = $datos['numero_inventario'] ?? null;
            if ($emitir && ! $alta && $equipoId === null && is_string($numeroInventario)
                && (! $ingreso->emitido() || $ingreso->numero_inventario !== $numeroInventario)
                && $this->equipos->buscarNumeroInventario($numeroInventario)->exists()) {
                throw ValidationException::withMessages([
                    'datos.numero_inventario' => 'Este número ya está en Inventario. Selecciona el equipo en la búsqueda.',
                ]);
            }

            if ($reintentoAlta) {
                $equipoId = $ingreso->equipo_id;
            }

            $ingreso->fill($datos);
            $ingreso->equipo_id = $equipoId;

            // The first issuance assigns the workshop equipment ID, so request retries may still omit it.
            if ($reintento && $emitir && $equipoId === null && $equipoTallerId === null) {
                $equipoTallerId = $ingreso->getOriginal('equipo_taller_id');
            }

            $ingreso->equipo_taller_id = $equipoTallerId;
            $cambio = $ingreso->isDirty();

            if ($reintento && ($cambio || ($emitir && ! $ingreso->emitido()))) {
                $this->verificarVersion($ingreso, $version);
            }

            abort_if(! $emitir && $ingreso->emitido() && $cambio, 409);

            if ($emitir && (! $ingreso->emitido() || $cambio)) {
                if ($alta && ! $ingreso->emitido()) {
                    $equipoTaller = $equipoTallerId === null ? null : $this->equipoTallerBloqueado($equipoTallerId);
                    if ($equipoTaller?->equipo_id !== null) {
                        throw ValidationException::withMessages([
                            'datos.numero_inventario' => 'Este equipo ya fue vinculado a Inventario. Selecciónalo en la búsqueda.',
                        ]);
                    }

                    $equipo = $this->equipos->crearDesdeTaller($datos, $tecnico->id, 'datos');
                    $ingreso->equipo_id = $equipo->id;
                    if ($equipoTaller !== null) {
                        $this->asociarEquipo($equipoTaller, $equipo);
                    }
                } elseif ($equipoId === null && $equipoTallerId === null) {
                    $ingreso->equipo_taller_id = EquipoTaller::create()->id;
                }

                $ingreso->folio ??= 'T-'.str_pad((string) $ingreso->id, 4, '0', STR_PAD_LEFT);
                $ingreso->revision++;
            }

            if ($ingreso->isDirty()) {
                $ingreso->version++;
                $ingreso->save();
            }

            return $ingreso;
        }, 3);
    }

    public function adjuntarFirma(int $ingresoId, int $version, UploadedFile $archivo): void
    {
        $ingreso = IngresoTaller::query()->findOrFail($ingresoId);
        $this->verificarVersion($ingreso, $version);
        $this->validarPendienteDeFirma($ingreso);

        $ruta = $archivo->store('taller/ingresos/'.$ingresoId, self::DISCO_FIRMAS);

        if ($ruta === false) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo guardar la hoja firmada. Intenta adjuntarla de nuevo.']);
        }

        try {
            DB::transaction(function () use ($ingresoId, $version, $ruta): void {
                $ingreso = IngresoTaller::query()->lockForUpdate()->findOrFail($ingresoId);
                $this->verificarVersion($ingreso, $version);
                $this->validarPendienteDeFirma($ingreso);
                $ingreso->archivo_firmado = $ruta;
                $ingreso->firmado_en = now();
                $ingreso->version++;
                $ingreso->save();
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk(self::DISCO_FIRMAS)->delete($ruta);

            throw $exception;
        }
    }

    public function vincularInventario(int $ingresoId, int $version, int $equipoId): void
    {
        DB::transaction(function () use ($ingresoId, $version, $equipoId): void {
            $ingreso = IngresoTaller::query()->lockForUpdate()->findOrFail($ingresoId);
            $this->verificarVersion($ingreso, $version);
            abort_unless($ingreso->emitido() && $ingreso->equipo_taller_id !== null, 409);

            $equipoTaller = $this->equipoTallerBloqueado($ingreso->equipo_taller_id);

            if ($equipoTaller->equipo_id === $equipoId) {
                return;
            }

            if ($equipoTaller->equipo_id !== null) {
                throw ValidationException::withMessages(['vinculo' => 'Este equipo de Taller ya fue vinculado a Inventario.']);
            }

            $this->asociarEquipo($equipoTaller, Equipo::query()->findOrFail($equipoId));
        }, 3);
    }

    /** @param array<string, mixed> $datos */
    public function darDeAltaIngreso(int $ingresoId, int $version, array $datos, int $usuarioId): Equipo
    {
        return DB::transaction(function () use ($ingresoId, $version, $datos, $usuarioId): Equipo {
            $ingreso = IngresoTaller::query()->lockForUpdate()->findOrFail($ingresoId);
            $this->verificarVersion($ingreso, $version);
            abort_unless($ingreso->emitido() && $ingreso->equipo_taller_id !== null, 409);
            $equipoTaller = $this->equipoTallerBloqueado($ingreso->equipo_taller_id);

            if ($equipoTaller->equipo_id !== null) {
                $equipo = Equipo::query()->findOrFail($equipoTaller->equipo_id);
                $datos = RegistrarEquipo::normalizar($datos);
                if (strcasecmp($equipo->numero_inventario, (string) ($datos['numero_inventario'] ?? '')) === 0
                    && strcasecmp((string) $equipo->numero_serie, (string) ($datos['numero_serie'] ?? '')) === 0) {
                    return $equipo;
                }

                throw ValidationException::withMessages(['vinculo' => 'Este equipo de Taller ya fue vinculado a Inventario.']);
            }

            $equipo = $this->equipos->crearDesdeTaller($datos, $usuarioId, 'datosAlta');
            $this->asociarEquipo($equipoTaller, $equipo);

            return $equipo;
        }, 3);
    }

    private function equipoTallerBloqueado(int $equipoTallerId): EquipoTaller
    {
        return EquipoTaller::query()->lockForUpdate()->findOrFail($equipoTallerId);
    }

    private function asociarEquipo(EquipoTaller $equipoTaller, Equipo $equipo): void
    {
        $equipoTaller->equipo()->associate($equipo);
        RegistrarAuditoria::guardarActualizacion($equipoTaller);
    }

    /** @param array<string, string|int|bool|null> $datos
     * @return array<string, string|int|bool|null>
     */
    private function completarDatos(IngresoTaller $ingreso, array $datos, ?int $equipoId): array
    {
        if ($equipoId !== null) {
            $usarGuardado = $ingreso->equipo_id === $equipoId && ($ingreso->emitido() || $ingreso->categoria_nombre !== null);
            $datos = array_replace($datos, $usarGuardado
                ? $ingreso->only([...IngresoTaller::DATOS_EQUIPO, 'categoria_nombre'])
                : $this->datosInventario(Equipo::query()->findOrFail($equipoId)));
        }

        $departamentoId = isset($datos['departamento_id']) ? (int) $datos['departamento_id'] : null;
        $datos['departamento_nombre'] = match (true) {
            $departamentoId === null => null,
            $ingreso->departamento_id === $departamentoId && $ingreso->departamento_nombre !== null => $ingreso->departamento_nombre,
            default => Departamento::query()->findOrFail($departamentoId)->nombre,
        };

        return $datos;
    }

    private function validarEditable(IngresoTaller $ingreso): void
    {
        if ($ingreso->firmado()) {
            throw ValidationException::withMessages(['ingreso' => 'La hoja ya está firmada. Abre el ingreso nuevamente para consultarla.']);
        }
    }

    private function validarPendienteDeFirma(IngresoTaller $ingreso): void
    {
        abort_unless($ingreso->emitido(), 409);
        $this->validarEditable($ingreso);
    }

    private function verificarVersion(IngresoTaller $ingreso, int $version): void
    {
        if ($ingreso->version !== $version) {
            throw ValidationException::withMessages(['ingreso' => 'El ingreso cambió en otra sesión. Abre el registro nuevamente antes de continuar.']);
        }
    }
}
