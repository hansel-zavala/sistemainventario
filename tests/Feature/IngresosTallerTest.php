<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Equipos\Index as EquiposIndex;
use App\Livewire\Taller\Ingresos\Formulario;
use App\Livewire\Taller\Ingresos\Index as IngresosIndex;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\EquipoTaller;
use App\Models\IngresoTaller;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\RegistrarIngresoTaller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IngresosTallerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_list_and_form_are_available_to_workshop_roles_only(): void
    {
        $this->get(route('taller.ingresos.index'))->assertRedirect(route('login'));

        $ingreso = IngresoTaller::factory()->emitido()->create();
        $this->login(User::factory()->create(['activo' => true]));
        $this->get(route('taller.ingresos.index'))->assertForbidden();
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertForbidden();
        $this->get(route('taller.ingresos.firmado', $ingreso))->assertForbidden();

        $inactivo = $this->usuario('Técnico');
        $inactivo->update(['activo' => false]);
        $this->login($inactivo);
        $this->get(route('taller.ingresos.nuevo'))->assertRedirect(route('login'));

        $this->login($this->usuario('Técnico'));
        $this->get(route('taller.ingresos.index'))->assertOk()->assertSee('Taller')->assertSee('Nuevo ingreso');
        $this->get(route('taller.ingresos.nuevo'))->assertOk()->assertSee('Ficha técnica')->assertSee('Pase de salida');
        $this->get(route('taller.ingresos.ver', $ingreso))->assertOk()->assertSee($ingreso->folio)->assertSee('Pendiente de firma');

        $this->login($this->usuario('Administrador'));
        $this->get(route('taller.ingresos.ver', $ingreso))->assertOk();
    }

    public function test_incomplete_draft_can_be_reopened_and_issued_using_the_same_record(): void
    {
        $usuario = $this->usuario('Técnico');
        Livewire::actingAs($usuario)->test(Formulario::class)
            ->assertSet('tecnicoNombre', $usuario->name)
            ->assertSet('datos.fecha_ingreso', now()->format('Y-m-d\TH:i'))
            ->set('datos.alta_inventario', '0')
            ->set('datos.entregado_por', 'Ana Flores')
            ->assertSet('datos.autorizado_por', 'Ana Flores')
            ->call('guardarBorrador')->assertHasNoErrors();

        $ingreso = IngresoTaller::query()->sole();
        $this->assertSame('Borrador', $ingreso->estado());
        $this->assertSame($usuario->id, $ingreso->tecnico_id);
        $this->login($usuario);
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertNotFound();

        $tipoEquipo = TipoEquipo::factory()->create();
        $marca = Marca::factory()->create();
        $categoria = Categoria::factory()->create(['aplica_a' => 'equipo']);
        $tipoEquipo->categorias()->attach($categoria);
        $marca->categorias()->attach($categoria);
        Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $ingreso])
            ->assertSet('datos.entregado_por', 'Ana Flores')
            ->assertSee('select id="ingreso-tipo_equipo"', false)
            ->assertSee('select id="ingreso-marca"', false)
            ->assertSee('Número de serie')
            ->call('emitir')->assertHasErrors(['datos.departamento_id', 'datos.categoria_alta_id', 'datos.tipo_equipo', 'datos.marca', 'datos.autoriza_respaldo'])
            ->set('datos.categoria_alta_id', $categoria->id)
            ->assertSee($tipoEquipo->nombre)
            ->assertSee($marca->nombre)
            ->set('datos', [
                ...$this->datos(Departamento::factory()->create()->id),
                'categoria_alta_id' => $categoria->id,
                'tipo_equipo' => $tipoEquipo->nombre,
                'marca' => $marca->nombre,
            ])
            ->call('emitir')->assertHasNoErrors()
            ->assertRedirect(route('taller.ingresos.ver', $ingreso));

        $ingreso->refresh();
        $this->assertSame('T-0001', $ingreso->folio);
        $this->assertSame(1, $ingreso->revision);
        $this->assertSame($categoria->nombre, $ingreso->categoria_nombre);
        $this->assertNull($ingreso->numero_serie);
        $this->assertSame($tipoEquipo->nombre, $ingreso->tipo_equipo);
        $this->assertSame($marca->nombre, $ingreso->marca);
        $this->assertFalse($ingreso->autoriza_respaldo);
        $this->assertNull($ingreso->numero_inventario);
        $this->assertDatabaseCount('ingresos_taller', 1);
        $this->assertDatabaseEmpty('equipos');
    }

    public function test_issuing_an_inventory_intake_registers_the_equipment_and_retries_keep_one_record(): void
    {
        $usuario = $this->usuario('Técnico');
        $datos = $this->datosAlta();
        $tipoFuera = TipoEquipo::factory()->create();
        $marcaFuera = Marca::factory()->create();

        Livewire::actingAs($usuario)->test(Formulario::class)
            ->assertSet('datos.alta_inventario', '1')
            ->set('datos.categoria_alta_id', $datos['categoria_alta_id'])
            ->assertSee($datos['tipo_equipo'])->assertSee($datos['marca'])
            ->assertDontSee($tipoFuera->nombre)->assertDontSee($marcaFuera->nombre)
            ->set('datos', $datos)
            ->call('guardarBorrador')->assertHasNoErrors();

        $borrador = IngresoTaller::query()->sole();
        $this->assertFalse($borrador->emitido());
        $this->assertDatabaseEmpty('equipos');

        Livewire::test(Formulario::class, ['ingreso' => $borrador])
            ->assertSet('datos.alta_inventario', '1')
            ->assertSet('datos.categoria_alta_id', $datos['categoria_alta_id'])
            ->assertSet('datos.numero_serie', $datos['numero_serie'])
            ->call('emitir')->assertHasNoErrors();

        $ingreso = IngresoTaller::query()->sole();
        $equipo = Equipo::query()->sole();
        $this->assertSame($equipo->id, $ingreso->equipo_id);
        $this->assertSame($datos['numero_serie'], $equipo->numero_serie);
        $this->assertSame($datos['observaciones'], $equipo->observaciones);
        $this->assertDatabaseHas('auditorias', ['modelo' => Equipo::class, 'registro_id' => $equipo->id, 'accion' => 'creacion', 'usuario_id' => $usuario->id]);

        $reintento = app(RegistrarIngresoTaller::class)->guardar(
            $usuario, $borrador->id, $borrador->borrador_token, $borrador->version, $datos, null, null, true,
        );
        $this->assertSame($ingreso->folio, $reintento->folio);
        $this->assertSame(1, $reintento->revision);
        $this->assertDatabaseCount('equipos', 1);
        $this->assertDatabaseCount('ingresos_taller', 1);
        Livewire::test(EquiposIndex::class)->set('buscar', $equipo->numero_inventario)
            ->assertSee($equipo->numero_inventario)->call('verEquipo', $equipo->id)->assertSee($ingreso->folio);
    }

    public function test_inventory_intake_requires_creation_data_and_preserves_input_when_a_number_is_duplicate(): void
    {
        $usuario = $this->usuario('Técnico');
        $equipo = Equipo::factory()->create(['numero_inventario' => 'INV-YA-EXISTE', 'numero_serie' => 'SERIE-YA-EXISTE']);
        $datos = $this->datosAlta();
        $marcaIncompatible = Marca::factory()->create();

        Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('datos', [...$datos, 'categoria_alta_id' => null, 'numero_serie' => null])
            ->call('emitir')->assertHasErrors(['datos.categoria_alta_id', 'datos.numero_serie'])
            ->set('datos', [...$datos, 'marca' => $marcaIncompatible->nombre])
            ->call('emitir')->assertHasErrors('datos.marca')
            ->set('datos', [...$datos, 'numero_inventario' => 'inv-ya-existe'])
            ->call('emitir')->assertHasErrors('datos.numero_inventario')
            ->assertSet('datos.modelo', $datos['modelo'])
            ->set('datos.numero_inventario', $datos['numero_inventario'])
            ->set('datos.numero_serie', 'serie-ya-existe')
            ->call('emitir')->assertHasErrors('datos.numero_serie')
            ->assertSet('datos.numero_inventario', $datos['numero_inventario']);

        $this->assertDatabaseCount('equipos', 1);
        $this->assertDatabaseEmpty('ingresos_taller');
        $this->assertDatabaseMissing('equipos', ['numero_inventario' => $datos['numero_inventario']]);
    }

    public function test_manual_intakes_reject_inactive_and_unknown_catalog_values(): void
    {
        $tipoInactivo = TipoEquipo::factory()->create(['activo' => false]);
        $marcaInactiva = Marca::factory()->create(['activo' => false]);

        Livewire::actingAs($this->usuario('Técnico'))->test(Formulario::class)
            ->assertDontSee($tipoInactivo->nombre)
            ->assertDontSee($marcaInactiva->nombre)
            ->set('datos.tipo_equipo', $tipoInactivo->nombre)
            ->set('datos.marca', 'Marca de texto libre')
            ->call('guardarBorrador')->assertHasErrors(['datos.tipo_equipo' => 'in', 'datos.marca' => 'in'])
            ->set('datos.tipo_equipo', 'Tipo de texto libre')
            ->set('datos.marca', $marcaInactiva->nombre)
            ->call('guardarBorrador')->assertHasErrors(['datos.tipo_equipo' => 'in', 'datos.marca' => 'in']);

        $this->assertDatabaseEmpty('ingresos_taller');
    }

    public function test_workshop_only_filters_catalogs_and_keeps_a_serial_in_the_draft_and_intake(): void
    {
        $datos = $this->datosAlta();
        $otraCategoria = Categoria::factory()->create(['aplica_a' => 'equipo']);
        $otraMarca = Marca::factory()->create();
        $otroTipo = TipoEquipo::factory()->create();
        $otraMarca->categorias()->attach($otraCategoria);
        $otroTipo->categorias()->attach($otraCategoria);
        $usuario = $this->usuario('Técnico');

        Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('datos', [...$datos, 'alta_inventario' => '0', 'numero_inventario' => null])
            ->assertSee($datos['marca'])->assertDontSee($otraMarca->nombre)
            ->set('datos.categoria_alta_id', $otraCategoria->id)
            ->assertSet('datos.tipo_equipo', null)->assertSet('datos.marca', null)
            ->assertSet('datos.numero_serie', $datos['numero_serie'])
            ->assertSee($otroTipo->nombre)->assertSee($otraMarca->nombre)
            ->set('datos.tipo_equipo', $otroTipo->nombre)
            ->set('datos.marca', $datos['marca'])
            ->call('emitir')->assertHasErrors('datos.marca')
            ->set('datos.marca', $otraMarca->nombre)
            ->call('guardarBorrador')->assertHasNoErrors();

        $ingreso = IngresoTaller::query()->sole();
        Livewire::test(Formulario::class, ['ingreso' => $ingreso])
            ->assertSet('datos.categoria_alta_id', $otraCategoria->id)
            ->assertSet('datos.numero_serie', $datos['numero_serie'])
            ->call('emitir')->assertHasNoErrors();

        $otraCategoria->update(['nombre' => 'Categoría renombrada']);
        Livewire::test(Formulario::class, ['ingreso' => $ingreso->refresh()])
            ->assertSee($ingreso->categoria_nombre)
            ->assertSee($datos['numero_serie'])
            ->assertDontSee('Categoría renombrada');
        $this->assertDatabaseEmpty('equipos');
    }

    public function test_quick_catalogs_create_and_link_options_without_losing_the_intake_fields(): void
    {
        $usuario = $this->usuario('Técnico');
        $tipoExistente = TipoEquipo::factory()->create();
        $marcaExistente = Marca::factory()->create();
        $formulario = Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('datos.entregado_por', 'Ana Flores')
            ->set('datos.numero_serie', 'SERIE-RAPIDA')
            ->call('abrirModalCatalogo', 'tipo_equipo')
            ->set('nuevoNombreCatalogo', 'Impresora rápida')
            ->call('guardarCatalogoRapido')->assertHasErrors('nuevoNombreCatalogo')
            ->call('cerrarModalCatalogo')
            ->call('abrirModalCatalogo', 'categoria')
            ->set('nuevoNombreCatalogo', 'Equipos de impresión')
            ->call('guardarCatalogoRapido')->assertHasNoErrors();

        $categoria = Categoria::where('nombre', 'Equipos de impresión')->sole();
        $formulario->assertSet('datos.categoria_alta_id', $categoria->id)
            ->call('abrirModalCatalogo', 'tipo_equipo')
            ->set('nuevoNombreCatalogo', 'Impresora rápida')
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('datos.tipo_equipo', 'Impresora rápida')
            ->call('abrirModalCatalogo', 'marca')
            ->set('nuevoNombreCatalogo', 'Marca rápida')
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('datos.marca', 'Marca rápida')
            ->call('abrirModalCatalogo', 'marca')
            ->set('nuevoNombreCatalogo', mb_strtolower($marcaExistente->nombre))
            ->call('guardarCatalogoRapido')->assertHasErrors('nuevoNombreCatalogo')
            ->set('vincularExistente', true)
            ->set('catalogoExistenteId', (string) $marcaExistente->id)
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('datos.marca', $marcaExistente->nombre)
            ->call('abrirModalCatalogo', 'tipo_equipo')
            ->set('vincularExistente', true)
            ->set('catalogoExistenteId', (string) $tipoExistente->id)
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('datos.tipo_equipo', $tipoExistente->nombre)
            ->call('abrirModalCatalogo', 'departamento')
            ->set('nuevoNombreCatalogo', 'Compras')
            ->set('nuevaSiglaDepartamento', 'cmp')
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('datos.entregado_por', 'Ana Flores')
            ->assertSet('datos.numero_serie', 'SERIE-RAPIDA')
            ->call('guardarBorrador')->assertHasNoErrors();

        $departamento = Departamento::where('nombre', 'Compras')->sole();
        $this->assertSame('CMP', $departamento->sigla);
        $this->assertDatabaseHas('ingresos_taller', ['departamento_id' => $departamento->id, 'categoria_alta_id' => $categoria->id]);
        $this->assertSame([$categoria->id], TipoEquipo::where('nombre', 'Impresora rápida')->sole()->categorias()->get()->modelKeys());
        $this->assertSame([$categoria->id], Marca::where('nombre', 'Marca rápida')->sole()->categorias()->get()->modelKeys());
        $this->assertSame([$categoria->id], $tipoExistente->categorias()->get()->modelKeys());
        $this->assertSame([$categoria->id], $marcaExistente->categorias()->get()->modelKeys());
        $this->assertDatabaseHas('auditorias', ['modelo' => Departamento::class, 'registro_id' => $departamento->id, 'accion' => 'creacion', 'usuario_id' => $usuario->id]);
    }

    public function test_new_issuance_retry_reuses_the_folio_and_manual_identity(): void
    {
        $tecnico = $this->usuario('Técnico');
        $this->actingAs($tecnico);
        $servicio = app(RegistrarIngresoTaller::class);
        $datos = [...$this->datosAlta(), 'alta_inventario' => '0', 'numero_inventario' => null, 'numero_serie' => null];
        $token = 'd46a3dc7-eb32-43c3-afdf-d560da12ff3d';
        $primero = $servicio->guardar($tecnico, null, $token, 0, $datos, null, null, true);
        $reintento = $servicio->guardar($tecnico, null, $token, 0, $datos, null, null, true);

        $this->assertSame($primero->folio, $reintento->folio);
        $this->assertSame($primero->equipo_taller_id, $reintento->equipo_taller_id);
        $this->assertSame(1, $reintento->revision);
        $this->assertDatabaseCount('ingresos_taller', 1);
        $this->assertDatabaseCount('equipos_taller', 1);
    }

    public function test_inventory_selection_prefills_the_form_without_changing_snapshots_when_catalog_data_changes(): void
    {
        $usuario = $this->usuario('Técnico');
        $equipo = Equipo::factory()->create(['numero_inventario' => 'INV-555', 'modelo' => 'Modelo original']);
        $equipo->tipoEquipo->categorias()->attach($equipo->categoria_id);
        $equipo->marca->categorias()->attach($equipo->categoria_id);
        $categoriaOriginal = $equipo->categoria->nombre;
        $datos = $this->datos($equipo->departamento_id);
        unset($datos['tipo_equipo'], $datos['numero_inventario']);

        $formulario = Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('datos', [...$datos, 'categoria_alta_id' => $equipo->categoria_id, 'tipo_equipo' => $equipo->tipoEquipo->nombre,
                'marca' => $equipo->marca->nombre, 'numero_inventario' => 'inv-555'])
            ->call('emitir')->assertHasErrors('datos.numero_inventario')
            ->set('buscarEquipo', 'inv-555')->assertSee('INV-555')
            ->call('seleccionarEquipo', $equipo->id)
            ->assertSet('datos.categoria_alta_id', $equipo->categoria_id)
            ->assertSet('datos.numero_serie', $equipo->numero_serie)
            ->assertSet('datos.modelo', 'Modelo original')
            ->assertSet('datos.departamento_id', $equipo->departamento_id);
        foreach ($datos as $campo => $valor) {
            $formulario->set('datos.'.$campo, $valor);
        }
        $formulario->call('emitir')->assertHasNoErrors();
        $ingreso = IngresoTaller::query()->sole();

        $equipo->update(['modelo' => 'Modelo modificado']);
        $equipo->categoria->update(['nombre' => 'Categoría de inventario renombrada']);
        $equipo->departamento->update(['nombre' => 'Departamento renombrado']);

        $this->login($usuario);
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertOk()->assertSee('Modelo original')
            ->assertSee($ingreso->departamento_nombre)->assertDontSee('Departamento renombrado')->assertDontSee('Modelo modificado');
        $this->assertSame($equipo->id, $ingreso->equipo_id);
        $this->assertSame($categoriaOriginal, $ingreso->categoria_nombre);
        $this->assertSame($equipo->numero_serie, $ingreso->numero_serie);
        $this->assertNull($ingreso->equipo_taller_id);
    }

    public function test_correcting_an_issued_record_changes_its_revision_and_refuses_a_stale_attachment(): void
    {
        $usuario = $this->usuario('Técnico');
        $motivo = str_repeat('Mantenimiento y revisión del sistema. ', 75).'Fin del informe.';
        $ingreso = IngresoTaller::factory()->emitido()->create(['motivo' => $motivo]);
        $sesionAnterior = Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $ingreso]);
        $this->login($usuario);
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertOk()->assertSee($motivo)
            ->assertSee('Departamento de Informática')->assertSee('Firma de quien autoriza');

        Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $ingreso])
            ->call('corregir')->assertSee($ingreso->tipo_equipo.' (valor guardado)')
            ->set('datos.numero_serie', 'SERIE-CORREGIDA')
            ->call('emitir')->assertHasErrors('datos.categoria_alta_id')
            ->set('datos.numero_serie', null)
            ->set('datos.motivo', 'Instalación de programas')
            ->call('emitir')->assertHasNoErrors();
        $folio = $ingreso->folio;
        $ingreso->refresh();
        $this->assertSame($folio, $ingreso->folio);
        $this->assertSame(2, $ingreso->revision);
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertOk()
            ->assertSee('Revisión 2')->assertSee('Instalación de programas')->assertDontSee('Fin del informe.');

        Storage::fake(RegistrarIngresoTaller::DISCO_FIRMAS);
        $sesionAnterior->set('archivo', $this->pdf())->set('confirmarFirma', true)
            ->call('adjuntarFirma')->assertHasErrors('ingreso');
        $this->assertFalse($ingreso->refresh()->firmado());
        $this->assertSame([], Storage::disk(RegistrarIngresoTaller::DISCO_FIRMAS)->allFiles('taller'));
    }

    public function test_signed_copy_is_validated_stored_privately_and_downloadable(): void
    {
        $disco = Storage::fake(RegistrarIngresoTaller::DISCO_FIRMAS);
        $usuario = $this->usuario('Técnico');
        $ingreso = IngresoTaller::factory()->emitido()->create();
        $this->login($usuario);
        $this->get(route('taller.ingresos.firmado', $ingreso))->assertNotFound();

        Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $ingreso])
            ->call('adjuntarFirma')->assertHasErrors(['archivo', 'confirmarFirma'])
            ->set('archivo', UploadedFile::fake()->createWithContent('hoja.txt', 'No es una hoja PDF'))
            ->set('confirmarFirma', true)->call('adjuntarFirma')->assertHasErrors('archivo')
            ->set('archivo', $this->pdf())->call('adjuntarFirma')->assertHasNoErrors();

        $ingreso->refresh();
        $this->assertSame('Firmado', $ingreso->estado());
        $disco->assertExists($ingreso->archivo_firmado);
        Storage::disk('public')->assertMissing($ingreso->archivo_firmado);
        $this->get(route('taller.ingresos.firmado', $ingreso))
            ->assertDownload($ingreso->folio.'-firmado.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('taller.ingresos.imprimir', $ingreso))->assertOk()->assertSee('Descargar hoja firmada');
        Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $ingreso])
            ->call('corregir')->assertStatus(409);
    }

    public function test_manual_history_can_be_reused_and_linked_without_rewriting_the_documents(): void
    {
        $usuario = $this->usuario('Técnico');
        $equipo = Equipo::factory()->create(['numero_inventario' => 'INV-LINK-002']);
        $primero = IngresoTaller::factory()->emitido()->create(['marca' => 'Marca previa']);
        $formulario = Livewire::actingAs($usuario)->test(Formulario::class)
            ->set('folioAnterior', $primero->folio)->call('recuperarEquipo')
            ->assertSet('datos.marca', 'Marca previa')
            ->assertSet('equipoTallerId', $primero->equipo_taller_id);
        $clasificacion = $this->datosAlta();
        $formulario->set('datos', [
            ...$this->datos($primero->departamento_id),
            ...array_intersect_key($clasificacion, array_flip(['categoria_alta_id', 'tipo_equipo', 'marca'])),
        ])
            ->call('emitir')->assertHasNoErrors();
        $segundo = IngresoTaller::query()->latest('id')->firstOrFail();
        $this->assertNotSame($primero->folio, $segundo->folio);
        $this->assertSame($primero->equipo_taller_id, $segundo->equipo_taller_id);
        $directo = IngresoTaller::factory()->emitido()->create([
            'equipo_taller_id' => null,
            'equipo_id' => $equipo->id,
            'numero_inventario' => $equipo->numero_inventario,
        ]);

        Livewire::test(Formulario::class, ['ingreso' => $segundo])
            ->assertSee('Historial técnico del equipo')->assertSee($primero->folio)->assertDontSee($directo->folio)
            ->set('buscarVinculo', 'INV-LINK')->assertSee('INV-LINK-002')
            ->call('seleccionarVinculo', $equipo->id)->assertSee('Confirmar vínculo')
            ->call('vincularInventario')->assertHasNoErrors()->assertSee('Vinculado en Inventario')->assertSee($directo->folio);
        $this->assertSame($equipo->id, EquipoTaller::query()->sole()->equipo_id);
        $this->assertNull($primero->refresh()->numero_inventario);
        $this->assertSame('Marca previa', $primero->marca);

        Livewire::test(IngresosIndex::class)->set('buscar', 'INV-LINK-002')->assertSee($primero->folio)->assertSee($segundo->folio)
            ->set('buscar', $primero->folio)->assertSee($primero->folio)->assertDontSee($segundo->folio);
        Livewire::test(EquiposIndex::class)->call('verEquipo', $equipo->id)
            ->assertSee('Historial técnico')->assertSee($primero->folio)->assertSee($segundo->folio)->assertSee($directo->folio);
        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(EquiposIndex::class)->call('verEquipo', $equipo->id)
            ->assertSee('Historial técnico')->assertDontSee($primero->folio)->assertDontSee($segundo->folio);
    }

    public function test_an_existing_signed_intake_can_register_equipment_and_keep_its_signed_document_and_history(): void
    {
        $usuario = $this->usuario('Técnico');
        $datos = $this->datosAlta();
        $anterior = IngresoTaller::factory()->emitido()->create([
            'departamento_id' => $datos['departamento_id'],
            'tipo_equipo' => $datos['tipo_equipo'],
            'marca' => $datos['marca'],
            'modelo' => $datos['modelo'],
            'color' => $datos['color'],
            'observaciones' => $datos['observaciones'],
        ]);
        $otraVisita = IngresoTaller::factory()->emitido()->create(['equipo_taller_id' => $anterior->equipo_taller_id]);
        $disco = Storage::fake(RegistrarIngresoTaller::DISCO_FIRMAS);

        Livewire::actingAs($usuario)->test(Formulario::class, ['ingreso' => $anterior])
            ->set('archivo', $this->pdf())->set('confirmarFirma', true)->call('adjuntarFirma')->assertHasNoErrors();
        $original = $anterior->refresh()->getAttributes();

        Livewire::test(Formulario::class, ['ingreso' => $anterior])
            ->call('abrirAlta')
            ->assertSet('datosAlta.departamento_id', $datos['departamento_id'])
            ->assertSet('datosAlta.tipo_equipo', $datos['tipo_equipo'])
            ->assertSet('datosAlta.marca', $datos['marca'])
            ->assertSet('datosAlta.observaciones', $datos['observaciones'])
            ->set('datosAlta.categoria_alta_id', $datos['categoria_alta_id'])
            ->set('datosAlta.numero_inventario', $datos['numero_inventario'])
            ->set('datosAlta.numero_serie', $datos['numero_serie'])
            ->call('darDeAlta')->assertHasNoErrors()->assertRedirect(route('taller.ingresos.ver', $anterior));

        $equipo = Equipo::query()->sole();
        $this->assertSame($equipo->id, $anterior->equipoTaller->equipo_id);
        $this->assertSame($original, $anterior->refresh()->getAttributes());
        $disco->assertExists($anterior->archivo_firmado);
        Livewire::test(EquiposIndex::class)->call('verEquipo', $equipo->id)->assertSee($anterior->folio)->assertSee($otraVisita->folio);
    }

    private function usuario(string $rol): User
    {
        $usuario = User::factory()->create(['activo' => true]);
        $usuario->assignRole(Role::findOrCreate($rol));

        return $usuario;
    }

    private function login(User $usuario): void
    {
        $this->actingAs($usuario)->withSession(['auth_window' => [
            'user_id' => $usuario->id,
            'expires_at' => now()->addHour()->timestamp,
            'password' => hash('sha256', $usuario->getAuthPassword()),
        ]]);
    }

    /** @return array<string, string|int|null> */
    private function datos(int $departamentoId): array
    {
        return [
            'fecha_ingreso' => '2026-11-27T09:45',
            'departamento_id' => $departamentoId,
            'entregado_por' => 'Ana Flores',
            'autorizado_por' => 'Ana Flores',
            'tipo_equipo' => 'Computadora',
            'numero_inventario' => null,
            'condicion_equipo' => 'Usado',
            'estado_fisico' => 'Bueno',
            'motivo' => 'Revisión del equipo',
            'autoriza_respaldo' => '0',
            'alta_inventario' => '0',
        ];
    }

    /** @return array<string, string|int|null> */
    private function datosAlta(): array
    {
        $categoria = Categoria::factory()->create(['aplica_a' => 'equipo', 'activo' => true]);
        $tipo = TipoEquipo::factory()->create();
        $marca = Marca::factory()->create();
        $tipo->categorias()->attach($categoria);
        $marca->categorias()->attach($categoria);

        return [
            ...$this->datos(Departamento::factory()->create()->id),
            'alta_inventario' => '1',
            'categoria_alta_id' => $categoria->id,
            'tipo_equipo' => $tipo->nombre,
            'marca' => $marca->nombre,
            'numero_inventario' => 'INV-ALTA-001',
            'numero_serie' => 'SERIE-ALTA-001',
            'modelo' => 'Modelo de prueba',
            'color' => 'Negro',
            'observaciones' => 'Incluye cargador.',
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('hoja.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }
}
