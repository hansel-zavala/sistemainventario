<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Herramientas\Index;
use App\Models\Departamento;
use App\Models\Herramienta;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HerramientasTableTest extends TestCase
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

    public function test_table_uses_its_own_columns_controls_and_accessible_actions(): void
    {
        $tool = Herramienta::factory()->create(['nombre' => 'Drill']);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->assertSee('herramientas.visibleColumns', false)->assertSee('Columnas')->assertSee('Exportar')
            ->assertSee(route('inventario.herramientas.exportar'))
            ->assertSee('herramientas-heading-cantidad_actual', false)->assertSee('herramientas-heading-activo', false)
            ->assertSee('Editar herramienta Drill')->assertSee('Desactivar herramienta Drill')->assertSee('Ver historial de Drill')
            ->call('editarHerramienta', $tool->id)->assertSet('herramientaId', $tool->id)->assertSet('mostrarModal', true)
            ->call('cerrarModal')->call('abrirHistorial', $tool->id)->assertSet('mostrarHistorial', true);
    }

    public function test_column_selection_does_not_hide_actions_or_change_availability(): void
    {
        Herramienta::factory()->create(['nombre' => 'Drill', 'cantidad_actual' => 3]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('setVisibleColumns', ['disponibilidad'])
            ->assertDontSee('herramientas-heading-cantidad_actual', false)->assertSee('3.00')->assertSee('Acciones')
            ->call('toggleColumn', 'disponibilidad')->assertSet('visibleColumns', ['disponibilidad'])
            ->call('setVisibleColumns', ['nombre', ['unexpected']])->assertSet('visibleColumns', ['nombre']);
    }

    public function test_sorting_cycles_resets_page_and_restores_the_existing_name_order(): void
    {
        $a = Herramienta::factory()->create(['nombre' => 'Alpha', 'cantidad_actual' => 10]);
        $z = Herramienta::factory()->create(['nombre' => 'Zulu', 'cantidad_actual' => 2]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->first()->id === $a->id)
            ->call('setPage', 2)->call('sortBy', 'cantidad_actual')->assertSet('paginators.page', 1)
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->first()->id === $z->id)
            ->call('sortBy', 'cantidad_actual')->assertSet('sortDirection', 'desc')
            ->assertSee('orden por nombre')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->first()->id === $a->id)
            ->call('sortBy', 'cantidad_actual')->assertSet('sortColumn', '')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->first()->id === $a->id);
    }

    public function test_sorting_uses_category_names_and_displayed_control_and_state_labels(): void
    {
        $quantity = Herramienta::factory()->create(['tipo_control' => 'cantidad', 'activo' => false]);
        $individual = Herramienta::factory()->create(['tipo_control' => 'individual', 'cantidad_actual' => 1]);
        $quantity->categoria->update(['nombre' => 'Zulu']);
        $individual->categoria->update(['nombre' => 'Alpha']);

        $component = Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class);
        foreach (['categoria', 'tipo_control', 'activo'] as $column) {
            $component->call('sortBy', $column)->assertSet('sortDirection', 'asc')
                ->assertViewHas('herramientas', fn ($rows): bool => $rows->first()->id === $individual->id);
        }
    }

    public function test_availability_sorting_accounts_for_partial_returns_and_inactive_tools(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $partial = Herramienta::factory()->create(['nombre' => 'Partial', 'cantidad_actual' => 10.5]);
        $free = Herramienta::factory()->create(['cantidad_actual' => 4]);
        $inactive = Herramienta::factory()->create(['activo' => false, 'cantidad_actual' => 100]);
        $borrowed = Herramienta::factory()->create(['tipo_control' => 'individual', 'cantidad_actual' => 1]);
        $this->loan($partial, 9, 1);
        $this->loan($borrowed, 1, 0);

        Livewire::actingAs($user)->test(Index::class)->call('sortBy', 'disponibilidad')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->pluck('id')->all() === [$borrowed->id, $inactive->id, $partial->id, $free->id])
            ->assertSee('2.50')->assertSee('8.00')->assertSee('Prestada')->assertSee('Inactiva');
    }

    public function test_filters_search_and_clear_preserve_pagination_behavior(): void
    {
        $match = Herramienta::factory()->create(['nombre' => 'Match', 'tipo_control' => 'individual', 'cantidad_actual' => 1]);
        Herramienta::factory()->create(['nombre' => 'Other', 'activo' => false]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->set('buscar', 'Match')->set('filtroTipo', 'individual')->set('filtroEstado', 'activo')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $match->id)
            ->call('setPage', 2)->set('filtroEstado', 'inactivo')->assertSet('paginators.page', 1)
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->total() === 0)
            ->call('clearFilters')->assertSet('buscar', '')->assertSet('filtroTipo', '')->assertSet('filtroEstado', '')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->total() === 2);
    }

    public function test_deactivation_still_blocks_pending_loans_and_audits_successful_changes(): void
    {
        $tool = Herramienta::factory()->create();
        $this->loan($tool, 2, 1);

        $component = Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('cambiarEstado', $tool->id)->assertHasErrors('estadoHerramienta');
        $this->assertTrue($tool->fresh()->activo);
        PrestamoDetalle::query()->update(['cantidad_devuelta' => 2, 'estado' => 'devuelto']);
        $component->call('cambiarEstado', $tool->id);
        $this->assertFalse($tool->fresh()->activo);
        $this->assertDatabaseHas('auditorias', ['modelo' => Herramienta::class, 'registro_id' => $tool->id, 'accion' => 'desactivacion']);
        $component->call('cambiarEstado', $tool->id);
        $this->assertTrue($tool->fresh()->activo);
        $this->assertDatabaseHas('auditorias', ['modelo' => Herramienta::class, 'registro_id' => $tool->id, 'accion' => 'activacion']);
    }

    #[TestWith(['sortBy'])]
    #[TestWith(['toggleColumn'])]
    public function test_unknown_columns_are_rejected(string $method): void
    {
        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call($method, 'unknown')->assertStatus(422);
    }

    private function loan(Herramienta $tool, float $quantity, float $returned): void
    {
        $department = Departamento::create(['nombre' => 'Department '.$tool->id, 'creado_por' => $tool->creado_por]);
        $loan = Prestamo::create([
            'departamento_destino_id' => $department->id, 'persona_recibe' => 'Test Recipient',
            'fecha_prestamo' => now(), 'fecha_prevista_devolucion' => now()->addDay(), 'registrado_por' => $tool->creado_por,
        ]);
        PrestamoDetalle::create([
            'prestamo_id' => $loan->id, 'herramienta_id' => $tool->id,
            'cantidad_prestada' => $quantity, 'cantidad_devuelta' => $returned, 'estado' => 'prestado',
        ]);
    }
}
