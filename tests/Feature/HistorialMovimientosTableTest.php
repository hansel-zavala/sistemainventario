<?php

namespace Tests\Feature;

use App\Livewire\Historial\Movimientos\Index;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\MovimientoInventario;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistorialMovimientosTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_filters_find_asset_codes_and_export_the_full_ordered_history(): void
    {
        $user = User::factory()->create(['name' => 'Ana Responsable', 'activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro', 'codigo_interno' => 'HT-427']);
        foreach (range(1, 11) as $day) {
            $movement = MovimientoInventario::create([
                'tipo' => 'entrada', 'fecha_movimiento' => sprintf('2026-11-%02d 23:59:59', $day),
                'registrado_por' => $user->id, 'observaciones' => sprintf('Entrega %02d', $day),
            ]);
            $movement->detalles()->create(['tipo_item' => 'herramienta', 'item_id' => $tool->id, 'cantidad' => 1, 'existencia_anterior' => 1, 'existencia_posterior' => 2]);
        }

        Livewire::actingAs($user)->test(Index::class)
            ->set('buscar', 'HT-427')->set('filtroTipo', 'entrada')->set('filtroArticuloTipo', 'herramienta')
            ->set('filtroResponsable', (string) $user->id)->set('desde', '2026-11-01')->set('hasta', '2026-11-11')
            ->assertSee('Mostrando 10 de 11 movimientos encontrados')->assertDontSee('Entrega 01')
            ->call('nextPage')->assertSee('Entrega 01')->set('orden', 'antiguos')->assertSee('Página 1 de 2')
            ->assertSee('Entrega 01')->assertDontSee('Entrega 11')
            ->set('desde', '2026-11-12')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('clearFilters')->assertSet('buscar', 'HT-427')->assertSet('orden', 'recientes');

        $params = ['buscar' => 'HT-427', 'filtroTipo' => 'entrada', 'filtroArticuloTipo' => 'herramienta',
            'filtroResponsable' => $user->id, 'desde' => '2026-11-01', 'hasta' => '2026-11-11', 'orden' => 'antiguos',
            'columns' => ['detalle', 'responsable', 'anterior', 'posterior']];
        $this->get(route('historial.movimientos.exportar', [...$params, 'format' => 'print']))->assertOk()
            ->assertSeeInOrder(['Entrega 01', 'Entrega 11'])->assertSee('Fecha del movimiento: 2026-11-01');
        $csv = $this->get(route('historial.movimientos.exportar', [...$params, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertCount(12, preg_split('/\r?\n/', trim($csv)));
        $xlsx = $this->get(route('historial.movimientos.exportar', [...$params, 'format' => 'xlsx']))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);
    }

    public function test_historical_returns_and_recorded_deliveries_appear_without_duplicates(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $department = Departamento::factory()->create();
        $equipment = Equipo::factory()->create(['numero_serie' => 'SERIE-HISTORIAL-01']);
        $loan = Prestamo::create(['departamento_destino_id' => $department->id, 'persona_recibe' => 'Recepción',
            'fecha_prestamo' => '2026-11-01', 'fecha_prevista_devolucion' => '2026-11-02', 'estado' => 'completado', 'registrado_por' => $user->id]);

        $modern = $loan->detalles()->create(['equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 1,
            'estado' => 'devuelto', 'fecha_devolucion' => '2026-11-03', 'recibido_por' => $user->id]);
        $modern->devoluciones()->create(['cantidad' => 1, 'fecha_devolucion' => '2026-11-03', 'recibido_por' => $user->id, 'observaciones' => 'Entrega registrada']);
        $loan->detalles()->create(['equipo_id' => $equipment->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 1,
            'estado' => 'devuelto', 'fecha_devolucion' => '2026-11-03', 'recibido_por' => $user->id]);

        Livewire::actingAs($user)->test(Index::class)->set('buscar', 'SERIE-HISTORIAL-01')->set('filtroTipo', 'devolucion')
            ->assertSee('Mostrando 2 de 2 movimientos encontrados')->assertSee('Entrega registrada')->assertSee('Devolucion historica');
    }
}
