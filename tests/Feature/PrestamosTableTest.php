<?php

namespace Tests\Feature;

use App\Livewire\Movimientos\Prestamos\Index;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrestamosTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_history_and_export_share_asset_destination_date_and_state_filters(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16));
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $archive = Departamento::factory()->create(['nombre' => 'Archivo histórico', 'activo' => false]);
        $office = Departamento::factory()->create();
        $equipment = Equipo::factory()->create(['numero_inventario' => 'EQ-HIST-42']);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro de respaldo']);
        $first = $this->loan($user, $archive, '2026-11-08 00:00:00', 'Primer préstamo');
        $last = $this->loan($user, $archive, '2026-11-08 23:59:59', 'Último préstamo');
        $outside = $this->loan($user, $office, '2026-11-08 15:00:00', 'Otro destino');

        foreach ([$first, $last, $outside] as $loan) {
            $loan->detalles()->create(['equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 0, 'estado' => 'prestado']);
        }
        $first->detalles()->create(['herramienta_id' => $tool->id, 'cantidad_prestada' => 3, 'cantidad_devuelta' => 1, 'estado' => 'prestado']);

        Livewire::actingAs($user)->test(Index::class)
            ->set('buscar', 'EQ-HIST-42')->set('tipoArticulo', 'herramienta_cantidad')->assertSee('0 resultados')
            ->set('tipoArticulo', 'equipo')->set('filtroEstado', 'vencido')->set('destino', (string) $archive->id)
            ->set('desde', '2026-11-08')->set('hasta', '2026-11-08')->set('orden', 'antiguos')
            ->assertSee('Departamento destino: Archivo histórico')->assertSee('Taladro de respaldo')
            ->assertSeeHtml('>Vencido</span>')
            ->assertViewHas('prestamos', fn ($rows): bool => $rows->pluck('id')->all() === [$first->id, $last->id])
            ->assertSee('Página 1 de 1')->assertSee('wire:click="nextPage" disabled', false)
            ->set('desde', '2026-11-09')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('clearFilters')->assertSet('buscar', 'EQ-HIST-42')->assertSet('orden', 'recientes');

        $this->get(route('movimientos.prestamos.exportar', [
            'format' => 'print', 'columns' => ['destino', 'articulo', 'observaciones'], 'buscar' => 'EQ-HIST-42',
            'tipoArticulo' => 'equipo', 'filtroEstado' => 'vencido', 'destino' => $archive->id,
            'desde' => '2026-11-08', 'hasta' => '2026-11-08', 'orden' => 'antiguos',
        ]))->assertOk()->assertSee('Archivo histórico')->assertSee('Taladro de respaldo')
            ->assertSeeInOrder(['Primer préstamo', 'Último préstamo'])->assertDontSee('Otro destino');
    }

    public function test_history_paginates_by_loan_and_resets_when_order_changes(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $department = Departamento::factory()->create();
        foreach (range(1, 11) as $day) {
            $this->loan($user, $department, sprintf('2026-11-%02d 00:00:00', $day), 'Registro '.$day);
        }

        Livewire::actingAs($user)->test(Index::class)
            ->assertSee('Mostrando 10 de 11 préstamos encontrados')->call('nextPage')->assertSee('Página 2 de 2')
            ->set('orden', 'antiguos')->assertSet('paginators.page', 1)->assertSee('Página 1 de 2');
    }

    public function test_active_loan_card_shows_pending_units_and_all_asset_details(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(12, 0));
        $user = User::factory()->create(['name' => 'Usuario de inventario', 'activo' => true]);
        $department = Departamento::factory()->create(['nombre' => 'Archivo']);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro de banco', 'tipo_control' => 'cantidad', 'codigo_interno' => 'HT-042']);
        $equipment = Equipo::factory()->create(['modelo' => 'Equipo de prueba', 'numero_inventario' => 'EQ-HIST-42']);
        $loan = $this->loan($user, $department, '2026-11-08 08:34:00', 'Verificar la entrega.');
        $loan->detalles()->createMany([
            ['herramienta_id' => $tool->id, 'cantidad_prestada' => 3.5, 'cantidad_devuelta' => 1.25, 'estado' => 'prestado'],
            ['equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 0, 'estado' => 'prestado'],
        ]);

        $history = Livewire::actingAs($user)->test(Index::class);

        $history->assertSeeHtmlInOrder([
            "Préstamo #{$loan->id}", 'Activo', '08/11/2026 08:34 AM', 'Devolución prevista',
            '10/11/2026 12:00 AM', 'Ver artículos', 'Ocultar detalle',
            'Destino', 'Archivo', 'Recibe', 'Ana Responsable', 'Registrado por', 'Usuario de inventario',
            '2 artículos prestados', '3.25 unidades pendientes de devolución',
            'Activo / Código', 'Prestado', 'Devuelto', 'Pendiente',
            'Taladro de banco', 'Herramienta por cantidad · HT-042', '3.5', '1.25', '2.25',
            'Equipo de prueba', 'Equipo · EQ-HIST-42', '2 artículos en el préstamo', 'Devolución pendiente', 'Verificar la entrega.',
        ])->assertSeeHtml('aria-controls="prestamo-'.$loan->id.'-detail"');
    }

    public function test_completed_loan_card_summarizes_the_single_returned_asset(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16));
        $user = User::factory()->create(['activo' => true]);
        $department = Departamento::factory()->create();
        $tool = Herramienta::factory()->create(['nombre' => 'Martillo', 'codigo_interno' => 'MAR-02']);
        $loan = $this->loan($user, $department, '2026-11-08 08:34:00', 'Entrega completada.');
        $loan->update(['estado' => 'completado']);
        $loan->detalles()->create(['herramienta_id' => $tool->id, 'cantidad_prestada' => 4, 'cantidad_devuelta' => 4, 'estado' => 'devuelto']);

        $history = Livewire::actingAs($user)->test(Index::class);

        $history->assertSeeHtmlInOrder([
            "Préstamo #{$loan->id}", 'Completado', 'Ver artículos', 'Martillo', 'Todos los artículos han sido devueltos',
            'Activo / Código', 'Martillo', 'MAR-02', '1 artículo en el préstamo', 'Sin pendientes',
        ])->assertDontSeeText('Devolución pendiente');
    }

    private function loan(User $user, Departamento $department, string $date, string $notes): Prestamo
    {
        return Prestamo::create([
            'departamento_destino_id' => $department->id, 'persona_recibe' => 'Ana Responsable',
            'fecha_prestamo' => $date, 'fecha_prevista_devolucion' => '2026-11-10',
            'estado' => 'activo', 'registrado_por' => $user->id, 'observaciones' => $notes,
        ]);
    }
}
