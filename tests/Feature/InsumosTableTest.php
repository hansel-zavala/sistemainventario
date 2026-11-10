<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Insumos\Index;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InsumosTableTest extends TestCase
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

    public function test_table_preserves_columns_and_accessible_actions(): void
    {
        $item = Insumo::factory()->create(['nombre' => 'Cable']);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->assertSee('insumos.visibleColumns', false)->assertSee('Columnas')->assertSee('Exportar')
            ->assertSee(route('inventario.insumos.exportar'))
            ->assertSee('insumos-heading-stock_minimo', false)->assertSee('insumos-heading-marca', false)
            ->assertSee('Editar insumo Cable')->assertSee('Desactivar insumo Cable')->assertSee('Ver historial de Cable')
            ->call('editarInsumo', $item->id)->assertSet('insumoId', $item->id)->assertSet('mostrarModal', true)
            ->call('cerrarModal')->call('abrirHistorial', $item->id)->assertSet('mostrarHistorial', true);
    }

    public function test_columns_can_be_hidden_without_hiding_actions_or_the_last_column(): void
    {
        Insumo::factory()->create(['nombre' => 'Cable']);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('setVisibleColumns', ['nombre'])
            ->assertDontSee('insumos-heading-cantidad_actual', false)->assertSee('Acciones')->assertSee('Cable')
            ->call('toggleColumn', 'nombre')->assertSet('visibleColumns', ['nombre'])
            ->call('toggleColumn', 'marca')->assertSet('visibleColumns', ['nombre', 'marca'])
            ->call('setVisibleColumns', ['nombre', 'invalid', ['unexpected']])->assertSet('visibleColumns', ['nombre']);
    }

    #[TestWith(['cantidad_actual'])]
    #[TestWith(['stock_minimo'])]
    public function test_numeric_sort_cycles_and_restores_the_original_name_order(string $column): void
    {
        $a = Insumo::factory()->create(['nombre' => 'Alpha', $column => 10]);
        $z = Insumo::factory()->create(['nombre' => 'Zulu', $column => 2.5]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $a->id)
            ->call('setPage', 2)->call('sortBy', $column)->assertSet('paginators.page', 1)
            ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $z->id)
            ->call('sortBy', $column)->assertSet('sortDirection', 'desc')->assertSee('orden por nombre')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $a->id)
            ->call('sortBy', $column)->assertSet('sortColumn', '')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $a->id);
    }

    public function test_related_columns_sort_by_names_and_status_sorts_by_its_label(): void
    {
        $z = Insumo::factory()->create(['activo' => false]);
        $a = Insumo::factory()->create();
        $z->categoria->update(['nombre' => 'Zulu']);
        $a->categoria->update(['nombre' => 'Alpha']);
        $z->update(['marca_id' => Marca::create(['nombre' => 'Zulu', 'creado_por' => $z->creado_por])->id]);
        $a->update(['marca_id' => Marca::create(['nombre' => 'Alpha', 'creado_por' => $a->creado_por])->id]);

        $component = Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class);
        foreach (['categoria', 'marca', 'activo'] as $column) {
            $component->call('sortBy', $column)->assertSet('sortDirection', 'asc')
                ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $a->id)
                ->call('sortBy', $column)
                ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $z->id);
        }
    }

    public function test_sorting_applies_before_pagination_and_has_stable_ties(): void
    {
        $first = Insumo::factory()->create(['cantidad_actual' => 0]);
        $ties = Insumo::factory()->count(10)->create(['cantidad_actual' => 5]);
        $last = Insumo::factory()->create(['cantidad_actual' => 20]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('sortBy', 'cantidad_actual')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->total() === 12 && $rows->first()->id === $first->id)
            ->call('sortBy', 'cantidad_actual')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->first()->id === $last->id && $rows->items()[1]->id === $ties->last()->id)
            ->call('setPage', 2)
            ->assertViewHas('insumos', fn ($rows): bool => $rows->last()->id === $first->id);
    }

    public function test_stock_alerts_and_units_survive_column_selection(): void
    {
        $item = Insumo::factory()->create(['cantidad_actual' => 1, 'stock_minimo' => 2]);
        $item->unidadMedida->update(['nombre_singular' => 'unidad', 'nombre_plural' => 'unidades']);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->assertSee('Stock bajo')->assertSee('unidad')->assertSee('unidades')->assertSee('—')
            ->call('setVisibleColumns', ['cantidad_actual'])->assertSee('Stock bajo')
            ->assertDontSee('insumos-heading-stock_minimo', false);
    }

    public function test_search_by_category_and_brand_combines_with_state_filter(): void
    {
        $match = Insumo::factory()->create();
        $match->categoria->update(['nombre' => 'Network']);
        $match->update(['marca_id' => Marca::create(['nombre' => 'Acme', 'creado_por' => $match->creado_por])->id]);
        Insumo::factory()->create(['nombre' => 'Other', 'activo' => false]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->set('buscar', 'Network')->set('filtroEstado', 'activo')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $match->id)
            ->set('buscar', 'Acme')->assertViewHas('insumos', fn ($rows): bool => $rows->total() === 1)
            ->call('setPage', 2)->set('filtroEstado', 'inactivo')->assertSet('paginators.page', 1)
            ->assertViewHas('insumos', fn ($rows): bool => $rows->total() === 0)
            ->call('clearFilters')->assertSet('buscar', '')->assertSet('filtroEstado', '')
            ->assertViewHas('insumos', fn ($rows): bool => $rows->total() === 2);
    }

    public function test_state_changes_still_preserve_inventory_and_create_audit_records(): void
    {
        $item = Insumo::factory()->create(['cantidad_actual' => 4.5]);

        $component = Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('cambiarEstado', $item->id)->assertSee('Insumo desactivado correctamente.');
        $this->assertDatabaseHas('insumos', ['id' => $item->id, 'activo' => false, 'cantidad_actual' => 4.5]);
        $this->assertDatabaseHas('auditorias', ['modelo' => Insumo::class, 'registro_id' => $item->id, 'accion' => 'desactivacion']);
        $component->call('cambiarEstado', $item->id)->assertSee('Insumo activado correctamente.');
        $this->assertTrue($item->fresh()->activo);
        $this->assertDatabaseHas('auditorias', ['modelo' => Insumo::class, 'registro_id' => $item->id, 'accion' => 'activacion']);
    }

    #[TestWith(['sortBy'])]
    #[TestWith(['toggleColumn'])]
    public function test_unknown_columns_are_rejected(string $method): void
    {
        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call($method, 'unknown')->assertStatus(422);
    }
}
