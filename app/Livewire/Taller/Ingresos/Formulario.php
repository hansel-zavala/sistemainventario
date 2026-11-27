<?php

namespace App\Livewire\Taller\Ingresos;

use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\IngresoTaller;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\RegistrarCatalogoEquipo;
use App\Services\RegistrarEquipo;
use App\Services\RegistrarIngresoTaller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const CATALOGOS_EQUIPO = [
        'tipo_equipo' => TipoEquipo::class,
        'marca' => Marca::class,
    ];

    #[Locked]
    public ?int $ingresoId = null;

    #[Locked]
    public ?int $equipoId = null;

    #[Locked]
    public ?int $equipoTallerId = null;

    #[Locked]
    public string $tecnicoNombre = '';

    #[Locked]
    public string $borradorToken = '';

    #[Locked]
    public int $version = 0;

    #[Locked]
    public bool $corrigiendo = false;

    #[Locked]
    public ?int $equipoVinculoId = null;

    #[Locked]
    public ?string $ultimoEntregadoPor = null;

    /** @var array<string, string|null> */
    #[Locked]
    public array $catalogoGuardado = [];

    /** @var array<string, string|int|bool|null> */
    public array $datos = [];

    #[Locked]
    public bool $mostrarAlta = false;

    /** @var array<string, string|int|null> */
    public array $datosAlta = [];

    #[Locked]
    public string $tipoCatalogo = '';

    #[Locked]
    public string $grupoCatalogo = 'datos';

    public string $nuevoNombreCatalogo = '';

    public string $nuevaSiglaDepartamento = '';

    public bool $vincularExistente = false;

    public string $catalogoExistenteId = '';

    protected RegistrarCatalogoEquipo $catalogos;

    public string $buscarEquipo = '';

    public string $folioAnterior = '';

    public string $buscarVinculo = '';

    public bool $confirmarFirma = false;

    public ?TemporaryUploadedFile $archivo = null;

    public function boot(RegistrarCatalogoEquipo $catalogos): void
    {
        Gate::authorize('acceder-taller');
        $this->catalogos = $catalogos;
    }

    public function mount(RegistrarIngresoTaller $registro, ?IngresoTaller $ingreso = null): void
    {
        if ($ingreso?->exists) {
            $this->cargarIngreso($ingreso);
            if (! $ingreso->emitido() && $ingreso->equipo_id !== null && $ingreso->categoria_nombre === null) {
                $this->datos = array_replace($this->datos, $registro->datosInventario(Equipo::query()->findOrFail($ingreso->equipo_id)));
            }

            return;
        }

        $this->borradorToken = (string) Str::uuid();
        $this->tecnicoNombre = Auth::user()->name;
        $this->datos = array_fill_keys(IngresoTaller::DATOS_FORMULARIO, null);
        $this->datos['fecha_ingreso'] = now()->format('Y-m-d\TH:i');
        $this->datos['alta_inventario'] = '1';
    }

    public function updatedDatos(mixed $value, ?string $campo): void
    {
        if ($campo === 'categoria_alta_id' && $this->equipoId === null) {
            $this->datos['categoria_nombre'] = null;
            $this->datos = $this->conservarOpcionesCompatibles($this->datos);
            $this->resetValidation(['datos.categoria_alta_id', 'datos.tipo_equipo', 'datos.marca']);
        }

        if ($campo === 'entregado_por' && is_string($value)) {
            $firmante = $this->datos['autorizado_por'] ?? null;
            if (blank($firmante) || $firmante === $this->ultimoEntregadoPor) {
                $this->datos['autorizado_por'] = trim($value);
            }

            $this->ultimoEntregadoPor = trim($value);
        }
    }

    public function updatedDatosAlta(mixed $value, ?string $campo): void
    {
        if ($campo === 'categoria_alta_id') {
            $this->datosAlta = $this->conservarOpcionesCompatibles($this->datosAlta);
            $this->resetValidation(['datosAlta.categoria_alta_id', 'datosAlta.tipo_equipo', 'datosAlta.marca']);
        }
    }

    public function abrirModalCatalogo(string $tipo, string $grupo = 'datos'): void
    {
        $this->validarContextoCatalogo($tipo, $grupo);
        $this->cerrarModalCatalogo();
        $this->tipoCatalogo = $tipo;
        $this->grupoCatalogo = $grupo;
    }

    public function cerrarModalCatalogo(): void
    {
        $this->reset('tipoCatalogo', 'grupoCatalogo', 'nuevoNombreCatalogo', 'nuevaSiglaDepartamento', 'vincularExistente', 'catalogoExistenteId');
        $this->resetValidation(['nuevoNombreCatalogo', 'nuevaSiglaDepartamento', 'catalogoExistenteId']);
    }

    public function updatedVincularExistente(): void
    {
        $this->resetValidation(['nuevoNombreCatalogo', 'catalogoExistenteId']);
    }

    public function guardarCatalogoRapido(): void
    {
        $tipo = $this->tipoCatalogo;
        $grupo = $this->grupoCatalogo;
        $this->validarContextoCatalogo($tipo, $grupo);

        $vincular = $this->vincularExistente && RegistrarCatalogoEquipo::esVinculable($tipo);
        $this->validate($this->catalogos->reglas($tipo, $vincular), $this->catalogos->mensajes());
        $datos = $this->{$grupo};
        $categoriaId = $datos['categoria_alta_id'] ?? null;
        $opcion = $vincular
            ? $this->catalogos->vincular($tipo, (int) $this->catalogoExistenteId, $categoriaId)
            : $this->catalogos->crear($tipo, $this->nuevoNombreCatalogo, $this->nuevaSiglaDepartamento, $categoriaId, (int) Auth::id());

        $campo = match ($tipo) {
            'categoria' => 'categoria_alta_id',
            'departamento' => 'departamento_id',
            default => $tipo,
        };
        $datos[$campo] = RegistrarCatalogoEquipo::esVinculable($tipo) ? $opcion->nombre : $opcion->id;
        if ($tipo === 'categoria') {
            $datos['categoria_nombre'] = $opcion->nombre;
        }

        $this->{$grupo} = $tipo === 'categoria' ? $this->conservarOpcionesCompatibles($datos) : $datos;
        $this->resetValidation($tipo === 'categoria' ? [$grupo.'.'.$campo, $grupo.'.tipo_equipo', $grupo.'.marca'] : $grupo.'.'.$campo);
        $this->cerrarModalCatalogo();
        session()->flash('taller_mensaje', $vincular ? 'Opción vinculada y seleccionada correctamente.' : 'Registro creado y seleccionado correctamente.');
    }

    private function validarContextoCatalogo(string $tipo, string $grupo): void
    {
        abort_unless(RegistrarCatalogoEquipo::permite($tipo) && in_array($grupo, ['datos', 'datosAlta'], true), 422);

        if ($grupo === 'datos') {
            $this->validarEdicion();
            abort_unless($this->equipoId === null || $tipo === 'departamento', 409);

            return;
        }

        $ingreso = $this->ingreso();
        abort_unless($this->mostrarAlta && ! $this->corrigiendo && $ingreso?->emitido()
            && $ingreso->equipoTaller !== null && $ingreso->equipoTaller->equipo_id === null, 409);
    }

    public function seleccionarEquipo(int $equipoId, RegistrarIngresoTaller $registro): void
    {
        $this->validarEdicion();
        $equipo = Equipo::query()->findOrFail($equipoId);
        $this->equipoId = $equipo->id;
        $this->equipoTallerId = null;
        $this->catalogoGuardado = [];
        $this->datos = array_replace($this->datos, $registro->datosInventario($equipo), [
            'departamento_id' => $equipo->departamento_id,
            'alta_inventario' => '0',
        ]);
        $this->buscarEquipo = '';
        $this->folioAnterior = '';
        $this->resetValidation(['folioAnterior', 'datos.departamento_id', 'datos.tipo_equipo', 'datos.marca', 'datos.numero_inventario']);
    }

    public function recuperarEquipo(): void
    {
        $this->validarEdicion();
        $anterior = IngresoTaller::query()
            ->where('folio', Str::upper(trim($this->folioAnterior)))
            ->whereRelation('equipoTaller', 'equipo_id', null)
            ->first();

        if ($anterior === null) {
            $this->addError('folioAnterior', 'Escribe el folio de un ingreso de un equipo registrado en Taller que aún no esté vinculado a Inventario.');

            return;
        }

        $this->equipoId = null;
        $this->equipoTallerId = $anterior->equipo_taller_id;
        $this->catalogoGuardado = $anterior->only(array_keys(self::CATALOGOS_EQUIPO));
        $this->datos = array_replace($this->datos, $anterior->only(IngresoTaller::DATOS_EQUIPO));
        $this->datos['categoria_nombre'] = $anterior->categoria_nombre;
        if (filled($this->datos['categoria_alta_id'])) {
            $this->datos = $this->conservarOpcionesCompatibles($this->datos);
        }
        $this->datos['departamento_id'] = $anterior->departamento_id;
        $this->buscarEquipo = '';
        $this->folioAnterior = '';
        $this->resetValidation(['folioAnterior', 'datos.tipo_equipo', 'datos.marca']);
    }

    public function capturarOtroEquipo(): void
    {
        $this->validarEdicion();
        $this->equipoId = null;
        $this->equipoTallerId = null;
        $this->catalogoGuardado = [];
        $this->datos = array_replace($this->datos, array_fill_keys(IngresoTaller::DATOS_EQUIPO, null));
        $this->datos['alta_inventario'] = '1';
        $this->datos['categoria_nombre'] = null;
        $this->buscarEquipo = '';
        $this->folioAnterior = '';
    }

    public function guardarBorrador(RegistrarIngresoTaller $registro): void
    {
        abort_if($this->ingreso()?->emitido(), 409);
        $this->guardar($registro, emitir: false);
    }

    public function emitir(RegistrarIngresoTaller $registro): void
    {
        $this->guardar($registro, emitir: true);
    }

    public function corregir(): void
    {
        $ingreso = $this->ingreso();
        abort_unless($ingreso?->emitido() && ! $ingreso->firmado(), 409);
        $this->cargarIngreso($ingreso);
        $this->corrigiendo = true;
        $this->resetValidation();
    }

    public function cancelarCorreccion(): void
    {
        $ingreso = $this->ingreso();
        abort_unless($ingreso?->emitido(), 409);
        $this->cargarIngreso($ingreso);
        $this->corrigiendo = false;
        $this->resetValidation();
    }

    public function adjuntarFirma(RegistrarIngresoTaller $registro): void
    {
        abort_if($this->corrigiendo || $this->ingresoId === null, 409);
        $this->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'confirmarFirma' => ['accepted'],
        ], [
            'archivo.required' => 'Adjunta una foto o un PDF de la hoja firmada.',
            'archivo.mimes' => 'Adjunta un archivo PDF, JPG o PNG.',
            'archivo.max' => 'El archivo puede pesar hasta 10 MB.',
            'confirmarFirma.accepted' => 'Confirma que la copia contiene la firma y corresponde a esta revisión.',
        ]);

        $registro->adjuntarFirma($this->ingresoId, $this->version, $this->archivo);
        session()->flash('taller_mensaje', 'Se guardó la hoja firmada.');
        $this->redirectRoute('taller.ingresos.ver', ['ingreso' => $this->ingresoId]);
    }

    public function updatedBuscarVinculo(): void
    {
        $this->equipoVinculoId = null;
    }

    public function seleccionarVinculo(int $equipoId): void
    {
        abort_if($this->corrigiendo || $this->ingresoId === null, 409);
        $this->equipoVinculoId = Equipo::query()->findOrFail($equipoId)->id;
        $this->resetValidation('vinculo');
    }

    public function vincularInventario(RegistrarIngresoTaller $registro): void
    {
        abort_if($this->corrigiendo || $this->ingresoId === null || $this->equipoVinculoId === null, 409);
        $registro->vincularInventario($this->ingresoId, $this->version, $this->equipoVinculoId);
        $this->equipoVinculoId = null;
        $this->buscarVinculo = '';
        session()->flash('taller_mensaje', 'El equipo se vinculó a Inventario con todo su historial de Taller.');
    }

    public function abrirAlta(): void
    {
        $ingreso = $this->ingreso();
        abort_unless(! $this->corrigiendo && $ingreso?->emitido() && $ingreso->equipoTaller !== null
            && $ingreso->equipoTaller->equipo_id === null, 409);

        $this->datosAlta = $ingreso->only([
            ...IngresoTaller::DATOS_EQUIPO, 'departamento_id', 'observaciones',
        ]);
        if (filled($this->datosAlta['categoria_alta_id'])) {
            $this->datosAlta = $this->conservarOpcionesCompatibles($this->datosAlta);
        }
        $this->resetValidation();
        $this->mostrarAlta = true;
    }

    public function cancelarAlta(): void
    {
        $this->cerrarModalCatalogo();
        $this->reset('mostrarAlta', 'datosAlta');
        $this->resetValidation();
    }

    public function darDeAlta(RegistrarIngresoTaller $registro): void
    {
        abort_unless($this->mostrarAlta && ! $this->corrigiendo && $this->ingresoId !== null, 409);
        $this->resetValidation();

        $registro->darDeAltaIngreso($this->ingresoId, $this->version, $this->datosAlta, (int) Auth::id());
        session()->flash('taller_mensaje', 'El equipo se registró en Inventario y se vinculó a su historial de Taller.');
        $this->redirectRoute('taller.ingresos.ver', ['ingreso' => $this->ingresoId]);
    }

    public function render(RegistrarIngresoTaller $registro, RegistrarEquipo $equipos): View
    {
        $ingreso = $this->ingreso();
        $editable = ! $ingreso?->emitido() || $this->corrigiendo;
        $puedeVincular = ! $editable && $ingreso?->equipoTaller !== null && $ingreso->equipoTaller->equipo_id === null;
        $altaEnIngreso = $editable && $this->altaSolicitada();
        $altaAnterior = $puedeVincular && $this->mostrarAlta;

        $departamentoId = ($altaAnterior ? $this->datosAlta : $this->datos)['departamento_id'] ?? null;
        $departamentos = $editable || $altaAnterior ? Departamento::query()
            ->where(fn ($query) => $query->where('activo', true)->orWhereKey(is_numeric($departamentoId) ? (int) $departamentoId : 0))
            ->orderBy('nombre')->get(['id', 'nombre']) : collect();
        $clasificacionEditable = $editable && $this->equipoId === null;
        $categoriasAlta = $clasificacionEditable || $altaAnterior ? $equipos->categoriasActivas()->orderBy('nombre')->get(['id', 'nombre']) : collect();
        $opcionesCatalogo = $clasificacionEditable ? $this->catalogos->opciones($this->datos['categoria_alta_id'] ?? null) : [];
        $opcionesAlta = $altaAnterior ? $this->catalogos->opciones($this->datosAlta['categoria_alta_id'] ?? null) : [];
        $catalogoVinculable = RegistrarCatalogoEquipo::esVinculable($this->tipoCatalogo);
        $categoriaCatalogo = $catalogoVinculable ? $this->catalogos->categoriaActiva($this->{$this->grupoCatalogo}['categoria_alta_id'] ?? null) : null;
        $opcionesCatalogoExistente = $categoriaCatalogo !== null && $this->vincularExistente
            ? $this->catalogos->disponiblesParaVincular($this->tipoCatalogo) : collect();
        $resultadosEquipo = $editable && trim($this->buscarEquipo) !== '' ? $registro->buscarInventario($this->buscarEquipo)
            ->with(['tipoEquipo:id,nombre', 'marca:id,nombre'])->limit(8)->get() : collect();
        $resultadosVinculo = $puedeVincular && trim($this->buscarVinculo) !== '' ? $registro->buscarInventario($this->buscarVinculo)
            ->with(['tipoEquipo:id,nombre', 'marca:id,nombre'])->limit(8)->get() : collect();
        $equipoVinculo = $puedeVincular && $this->equipoVinculoId !== null ? Equipo::query()->find($this->equipoVinculoId) : null;
        $equipoVinculado = $ingreso?->equipoTaller?->equipo;
        $historial = null;

        if (! $editable) {
            $equipoInventarioId = $ingreso->equipo_id ?? $equipoVinculado?->id;
            $historial = IngresoTaller::query()->whereKeyNot($ingreso->id)
                ->when($equipoInventarioId !== null,
                    fn (Builder $query) => $query->historialEquipo($equipoInventarioId),
                    fn (Builder $query) => $query->where('equipo_taller_id', $ingreso->equipo_taller_id ?? 0)
                        ->whereNotNull('folio')->latest('fecha_ingreso')->latest('id'),
                )->paginate(6, pageName: 'historialTaller');
        }

        return view('livewire.taller.ingresos.formulario', compact(
            'ingreso', 'editable', 'departamentos', 'opcionesCatalogo', 'resultadosEquipo', 'puedeVincular', 'resultadosVinculo',
            'equipoVinculo', 'equipoVinculado', 'historial', 'altaEnIngreso', 'altaAnterior', 'categoriasAlta', 'opcionesAlta',
            'catalogoVinculable', 'categoriaCatalogo', 'opcionesCatalogoExistente',
        ));
    }

    private function guardar(RegistrarIngresoTaller $registro, bool $emitir): void
    {
        $this->validarEdicion();
        $this->resetValidation();

        $datos = array_map(fn ($valor) => is_string($valor) ? trim($valor) : $valor, Arr::only($this->datos, IngresoTaller::DATOS_FORMULARIO));
        $datos = array_replace(array_fill_keys(IngresoTaller::DATOS_FORMULARIO, null), $datos);
        if (blank($datos['autorizado_por'])) {
            $datos['autorizado_por'] = $datos['entregado_por'];
        }

        $presencia = $emitir ? 'required' : 'nullable';
        $clasificacion = $emitir && $this->equipoId === null && ! $this->corrigiendo ? 'required' : 'nullable';
        $serie = $emitir && $this->altaSolicitada() ? 'required' : 'nullable';
        $reglas = [
            'datos.fecha_ingreso' => [$presencia, 'date_format:Y-m-d\TH:i'],
            'datos.departamento_id' => [$presencia, 'integer', 'exists:departamentos,id'],
            'datos.entregado_por' => [$presencia, 'string', 'max:255'],
            'datos.tipo_equipo' => [$presencia, 'string', 'max:100'],
            'datos.numero_inventario' => ['nullable', 'string', 'max:100'],
            'datos.marca' => [$clasificacion, 'string', 'max:100'],
            'datos.alta_inventario' => ['nullable', 'boolean'],
            'datos.categoria_alta_id' => [$clasificacion, 'integer', 'exists:categorias,id'],
            'datos.numero_serie' => [$serie, 'string', 'max:100'],
            'datos.modelo' => ['nullable', 'string', 'max:100'],
            'datos.color' => ['nullable', 'string', 'max:50'],
            'datos.condicion_equipo' => [$presencia, Rule::in(IngresoTaller::CONDICIONES)],
            'datos.estado_fisico' => [$presencia, Rule::in(IngresoTaller::ESTADOS_FISICOS)],
            'datos.observaciones' => ['nullable', 'string', 'max:3000'],
            'datos.motivo' => [$presencia, 'string', 'max:3000'],
            'datos.autorizado_por' => [$presencia, 'string', 'max:255'],
            'datos.autoriza_respaldo' => [$presencia, 'boolean'],
        ];

        if ($this->equipoId === null) {
            foreach ($this->opcionesCatalogo() as $campo => $opciones) {
                $nombreGuardado = $this->catalogoGuardado[$campo] ?? null;
                if (filled($nombreGuardado)) {
                    $opciones[] = $nombreGuardado;
                }

                $reglas['datos.'.$campo][] = Rule::in($opciones);
            }
        }

        $validados = Validator::make(['datos' => $datos], $reglas, [
            'required' => 'Completa este campo para emitir la hoja.',
            'date_format' => 'Indica una fecha y hora válidas.',
            'exists' => 'Selecciona un departamento del catálogo.',
            'in' => 'Selecciona una de las opciones disponibles.',
            'datos.tipo_equipo.in' => 'Selecciona un tipo de equipo del catálogo.',
            'datos.marca.in' => 'Selecciona una marca del catálogo.',
            'datos.categoria_alta_id.exists' => 'Selecciona una categoría de equipos.',
            'boolean' => 'Selecciona Sí o No.',
            'max' => 'Usa hasta :max caracteres.',
            'string' => 'Escribe un texto válido.',
            'integer' => 'Selecciona un departamento del catálogo.',
        ])->validate()['datos'];
        $validados = array_map(fn ($valor) => $valor === '' ? null : $valor, $validados);

        /** @var User $tecnico */
        $tecnico = Auth::user();
        $ingreso = $registro->guardar(
            $tecnico, $this->ingresoId, $this->borradorToken, $this->version, $validados, $this->equipoId, $this->equipoTallerId, $emitir,
        );

        session()->flash('taller_mensaje', match (true) {
            ! $emitir => 'Se guardó el borrador.',
            $this->altaSolicitada() && $ingreso->equipo_id !== null => 'El equipo se registró en Inventario. La hoja de ingreso está lista para imprimir.',
            default => 'La hoja de ingreso está lista para imprimir.',
        });
        $this->redirectRoute('taller.ingresos.ver', ['ingreso' => $ingreso]);
    }

    /** @return array<string, list<string>> */
    private function opcionesCatalogo(): array
    {
        $opciones = [];

        foreach (self::CATALOGOS_EQUIPO as $campo => $modelo) {
            $opciones[$campo] = $modelo::query()->where('activo', true)->pluck('nombre')->all();
        }

        return $opciones;
    }

    private function altaSolicitada(): bool
    {
        return $this->equipoId === null && ! $this->corrigiendo
            && in_array($this->datos['alta_inventario'] ?? null, ['1', 1, true], true);
    }

    /** @param array<string, mixed> $datos */
    private function conservarOpcionesCompatibles(array $datos): array
    {
        foreach ($this->catalogos->opciones($datos['categoria_alta_id'] ?? null) as $campo => $opciones) {
            if (! in_array($datos[$campo] ?? null, $opciones, true)) {
                $datos[$campo] = null;
            }
        }

        return $datos;
    }

    private function ingreso(): ?IngresoTaller
    {
        return $this->ingresoId === null ? null : IngresoTaller::query()->findOrFail($this->ingresoId);
    }

    private function validarEdicion(): void
    {
        $ingreso = $this->ingreso();
        abort_if($ingreso?->firmado() || ($ingreso?->emitido() && ! $this->corrigiendo), 409);
    }

    private function cargarIngreso(IngresoTaller $ingreso): void
    {
        $this->ingresoId = $ingreso->id;
        $this->borradorToken = $ingreso->borrador_token;
        $this->equipoId = $ingreso->equipo_id;
        $this->equipoTallerId = $ingreso->equipo_taller_id;
        $this->tecnicoNombre = $ingreso->tecnico_nombre;
        $this->version = $ingreso->version;
        $this->ultimoEntregadoPor = $ingreso->entregado_por;
        $this->catalogoGuardado = $ingreso->only(array_keys(self::CATALOGOS_EQUIPO));
        $this->datos = $ingreso->only(IngresoTaller::DATOS_FORMULARIO);
        $this->datos['categoria_nombre'] = $ingreso->categoria_nombre ?? $ingreso->categoria?->nombre;
        $this->datos['fecha_ingreso'] = $ingreso->fecha_ingreso?->format('Y-m-d\TH:i');
        $this->datos['autoriza_respaldo'] = $ingreso->autoriza_respaldo === null ? null : (string) (int) $ingreso->autoriza_respaldo;
        $this->datos['alta_inventario'] = (string) (int) $ingreso->alta_inventario;
        $this->reset('buscarEquipo', 'folioAnterior', 'archivo', 'confirmarFirma', 'mostrarAlta', 'datosAlta');
        $this->cerrarModalCatalogo();
    }
}
