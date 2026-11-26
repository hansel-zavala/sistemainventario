<?php

namespace Tests\Feature;

use App\Livewire\Movimientos\Devoluciones\Index;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevolucionesTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_date_reference_filters_the_correct_records_and_keeps_pending_loans_accessible(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $department = Departamento::factory()->create(['nombre' => 'Archivo histórico', 'activo' => false]);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro', 'codigo_interno' => 'TAL-01']);
        $equipment = Equipo::factory()->create();
        $pending = $this->loan($user, $department, '2026-11-20 00:00:00');
        $older = $this->loan($user, $department, '2026-11-10 00:00:00');
        $newer = $this->loan($user, $department, '2026-11-09 00:00:00');
        $this->toolDetail($pending, $tool);
        $this->toolDetail($older, $tool)->devoluciones()->create(['fecha_devolucion' => '2026-11-17 00:00:00', 'cantidad' => 1, 'recibido_por' => $user->id, 'observaciones' => 'Entrega inicial']);
        $newerDetail = $this->toolDetail($newer, $tool);
        $newerDetail->devoluciones()->create(['fecha_devolucion' => '2026-11-17 23:59:59', 'cantidad' => 1, 'recibido_por' => $user->id, 'observaciones' => 'Entrega al cierre']);
        $newerDetail->devoluciones()->create(['fecha_devolucion' => '2026-11-18 00:00:00', 'cantidad' => 1, 'recibido_por' => $user->id, 'observaciones' => 'Fuera del rango']);
        $newer->detalles()->create(['equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 0, 'estado' => 'prestado']);

        Livewire::actingAs($user)->test(Index::class)->set('buscar', 'TAL-01')->set('destino', (string) $department->id)
            ->assertSee('Departamento destino: Archivo histórico')
            ->assertViewHas('prestamos', fn ($rows): bool => $rows->pluck('id')->all() === [$pending->id, $older->id, $newer->id])
            ->set('tipoArticulo', 'equipo')->assertSee('0 resultados')
            ->set('tipoArticulo', 'herramienta_cantidad')->set('tipoFecha', 'registrada')
            ->set('desde', '2026-11-17')->set('hasta', '2026-11-17')
            ->assertViewHas('prestamos', fn ($rows): bool => $rows->pluck('id')->all() === [$newer->id, $older->id])
            ->assertSee('Entrega al cierre')->assertDontSee('Fuera del rango')
            ->set('orden', 'antiguos')
            ->assertViewHas('prestamos', fn ($rows): bool => $rows->pluck('id')->all() === [$older->id, $newer->id])
            ->set('desde', '2026-11-18')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('clearFilters')->assertSet('buscar', 'TAL-01')->assertSet('tipoFecha', 'prevista')->assertSet('filtroEstado', 'pendientes');
    }

    public function test_export_selects_matching_deliveries_across_pages_and_includes_pending_assets_by_due_date(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $department = Departamento::factory()->create();
        $tool = Herramienta::factory()->create(['nombre' => 'Activo del reporte']);
        $individual = Herramienta::factory()->create(['nombre' => 'Herramienta individual fuera del reporte', 'tipo_control' => 'individual']);
        $pending = $this->loan($user, $department, '2026-11-20 00:00:00');
        $this->toolDetail($pending, $tool);
        foreach (range(1, 11) as $day) {
            $loan = $this->loan($user, $department, '2026-11-20 00:00:00');
            $this->toolDetail($loan, $tool)->devoluciones()->create(['fecha_devolucion' => sprintf('2026-11-%02d 08:00:00', $day), 'cantidad' => 1, 'recibido_por' => $user->id, 'observaciones' => sprintf('Entrega número %02d', $day)]);
        }
        $this->toolDetail($loan, $individual)->devoluciones()->create(['fecha_devolucion' => '2026-11-11 09:00:00', 'cantidad' => 1, 'recibido_por' => $user->id]);

        Livewire::actingAs($user)->test(Index::class)->set('tipoFecha', 'registrada')->set('tipoArticulo', 'herramienta_cantidad')
            ->assertSee('Mostrando 10 de 11 préstamos encontrados')->call('nextPage')->assertSee('Página 2 de 2');

        $params = ['columns' => ['articulo', 'fecha_registrada', 'observaciones'], 'desde' => '2026-11-01', 'hasta' => '2026-11-11', 'tipoFecha' => 'registrada', 'tipoArticulo' => 'herramienta_cantidad'];
        $this->get(route('movimientos.devoluciones.exportar', [...$params, 'format' => 'print']))->assertOk()
            ->assertSee('Fecha registrada: 2026-11-01')->assertSeeInOrder(['Entrega número 11', 'Entrega número 01'])
            ->assertDontSee('Herramienta individual fuera del reporte');
        $csv = $this->get(route('movimientos.devoluciones.exportar', [...$params, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertCount(12, preg_split('/\r?\n/', trim($csv)));
        $xlsx = $this->get(route('movimientos.devoluciones.exportar', [...$params, 'format' => 'xlsx']))
            ->assertOk()->assertDownload()->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);
        $this->get(route('movimientos.devoluciones.exportar', ['format' => 'print', 'columns' => ['prestamo', 'articulo', 'pendiente'], 'desde' => '2026-11-20', 'hasta' => '2026-11-20']))
            ->assertOk()->assertSee('<td>'.$pending->id.'</td>', false);
    }

    public function test_a_return_can_still_be_registered_from_the_loan_card(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(15, 1));
        $user = User::factory()->create(['name' => 'Usuario de inventario', 'activo' => true]);
        $department = Departamento::factory()->create();
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro de prueba']);
        $loan = $this->loan($user, $department, '2026-11-20 00:00:00');
        $detail = $this->toolDetail($loan, $tool);

        Livewire::actingAs($user)->test(Index::class)
            ->assertSeeHtmlInOrder([
                "Préstamo #{$loan->id}", 'Activo', 'Fecha prevista', 'Registrar devolución', 'Ver artículos e historial',
                '1 activo en el préstamo', '3 de 3', 'unidades pendientes', 'Historial de devoluciones',
                'Todavía no se han registrado devoluciones.',
            ])
            ->assertSeeHtml('wire:click="abrirDevolucion('.$loan->id.')"')
            ->call('abrirDevolucion', $loan->id)
            ->set('seleccionados.'.$detail->id, true)->set('cantidades.'.$detail->id, '1')
            ->set('observacionesDetalle.'.$detail->id, 'Entrega parcial.')
            ->call('guardarDevolucion')->assertHasNoErrors()->assertSee('Devolución registrada correctamente.')
            ->assertSeeHtmlInOrder([
                '2 de 3', 'unidades pendientes', 'Historial de devoluciones', 'Taladro de prueba · 1 unidad',
                '18/11/2026 03:01 PM', 'Por Usuario de inventario', 'Entrega parcial.',
            ]);

        $this->assertDatabaseHas('prestamo_devoluciones', ['prestamo_detalle_id' => $detail->id, 'cantidad' => 1, 'recibido_por' => $user->id]);
    }

    public function test_completed_loan_groups_return_events_for_different_assets_in_chronological_order(): void
    {
        $user = User::factory()->create(['name' => 'Usuario de inventario', 'activo' => true]);
        $department = Departamento::factory()->create(['nombre' => 'Catastro']);
        $tool = Herramienta::factory()->create(['nombre' => 'Martillo', 'codigo_interno' => 'MAR-02']);
        $equipment = Equipo::factory()->create(['modelo' => 'Portátil de prueba', 'numero_inventario' => 'EQ-042']);
        $loan = $this->loan($user, $department, '2026-11-20 00:00:00');
        $loan->update(['estado' => 'completado']);
        $toolDetail = $this->toolDetail($loan, $tool);
        $toolDetail->update(['cantidad_devuelta' => 3, 'estado' => 'devuelto']);
        $equipmentDetail = $loan->detalles()->create([
            'equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 1, 'estado' => 'devuelto',
        ]);
        $toolDetail->devoluciones()->create(['fecha_devolucion' => '2026-11-19 15:00:00', 'cantidad' => 2, 'recibido_por' => $user->id]);
        $equipmentDetail->devoluciones()->create(['fecha_devolucion' => '2026-11-18 09:00:00', 'cantidad' => 1, 'recibido_por' => $user->id]);
        $toolDetail->devoluciones()->create(['fecha_devolucion' => '2026-11-17 11:00:00', 'cantidad' => 1, 'recibido_por' => $user->id]);

        Livewire::actingAs($user)->test(Index::class)->set('filtroEstado', 'completados')
            ->assertSeeHtmlInOrder([
                "Préstamo #{$loan->id}", 'Completado', 'Catastro', 'Ver artículos e historial', 'Ocultar detalle',
                '2 activos en el préstamo', 'Todos los artículos fueron devueltos', 'Prestado', 'Devuelto', 'Pendiente',
                'Martillo', 'MAR-02', 'Portátil de prueba', 'EQ-042', 'Historial de devoluciones',
                'Martillo · 1 unidad', '17/11/2026 11:00 AM', 'Portátil de prueba · 1 unidad', '18/11/2026 09:00 AM',
                'Martillo · 2 unidades', '19/11/2026 03:00 PM',
            ])->assertDontSeeText('Registrar devolución')->assertDontSeeText('Todavía no se han registrado devoluciones.');
    }

    private function loan(User $user, Departamento $department, string $due): Prestamo
    {
        return Prestamo::create(['departamento_destino_id' => $department->id, 'persona_recibe' => 'Ana Responsable',
            'fecha_prestamo' => '2026-11-01 00:00:00', 'fecha_prevista_devolucion' => $due, 'estado' => 'activo', 'registrado_por' => $user->id]);
    }

    private function toolDetail(Prestamo $loan, Herramienta $tool): PrestamoDetalle
    {
        return $loan->detalles()->create(['herramienta_id' => $tool->id, 'cantidad_prestada' => 3, 'cantidad_devuelta' => 0, 'estado' => 'prestado']);
    }
}
