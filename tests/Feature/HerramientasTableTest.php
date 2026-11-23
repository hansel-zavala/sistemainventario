<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Herramientas\Index;
use App\Models\Departamento;
use App\Models\Herramienta;
use App\Models\MovimientoInventario;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\RegistrarAuditoria;
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
            ->assertSee('Editar herramienta Drill')->assertSee('Desactivar herramienta Drill')->assertSee('Ver herramienta Drill')
            ->call('editarHerramienta', $tool->id)->assertSet('herramientaId', $tool->id)->assertSet('mostrarModal', true)
            ->call('cerrarModal')->call('verHerramienta', $tool->id)->assertSet('herramientaDetalleId', $tool->id)
            ->assertSee('Información general')->assertSee('Historial técnico')->assertSee('Esta herramienta aún no tiene movimientos registrados.')
            ->call('editarHerramienta', $tool->id)->assertSet('herramientaDetalleId', null)->assertSet('mostrarModal', true);
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

        $component = Livewire::actingAs($user)->test(Index::class)->call('sortBy', 'disponibilidad')
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->pluck('id')->all() === [$borrowed->id, $inactive->id, $partial->id, $free->id])
            ->assertSee('2.50')->assertSee('8.00')->assertSee('Prestada')->assertSee('Inactiva');

        $component->call('verHerramienta', $partial->id)->assertSee('2.5 disponibles')
            ->assertSee('Cantidad prestada')->assertSee('Cantidad disponible')->assertSee('Stock mínimo')
            ->call('cerrarDetalle')->call('verHerramienta', $borrowed->id)
            ->assertViewHas('herramientaDetalle', fn (Herramienta $detalle): bool => $detalle->esta_prestada)
            ->assertDontSee('Cantidad disponible');
    }

    public function test_tool_movements_include_stock_availability_and_audited_changes_without_duplicate_returns(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $tool = Herramienta::factory()->create(['creado_por' => $user->id]);
        $other = Herramienta::factory()->create();
        foreach (['apertura', 'entrada', 'entrada', 'salida'] as $index => $type) {
            $movement = MovimientoInventario::create([
                'tipo' => $type, 'fecha_movimiento' => '2026-01-0'.($index + 1), 'registrado_por' => $user->id,
            ]);
            $movement->detalles()->create([
                'tipo_item' => 'herramienta', 'item_id' => $tool->id, 'cantidad' => 1, 'existencia_anterior' => 1, 'existencia_posterior' => 2,
            ]);
            $movement->detalles()->create([
                'tipo_item' => 'insumo', 'item_id' => $tool->id, 'cantidad' => 1, 'existencia_anterior' => 1, 'existencia_posterior' => 2,
            ]);
        }

        $loan = $this->loan($tool, 2, 2);
        $loan->prestamo->update(['fecha_prestamo' => '2026-01-05']);
        $returned = ['estado' => 'devuelto', 'fecha_devolucion' => '2026-01-06', 'recibido_por' => $user->id];
        $loan->update($returned);
        $loan->devoluciones()->create(['cantidad' => 2, 'fecha_devolucion' => '2026-01-06', 'recibido_por' => $user->id, 'observaciones' => 'Entrega registrada']);
        $loan->prestamo->detalles()->create([
            'herramienta_id' => $tool->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 1, ...$returned,
        ]);
        $this->loan($other, 1, 0);

        $this->actingAs($user);
        RegistrarAuditoria::guardarActualizacion($tool->fill(['observaciones' => 'Verificación de inventario']));
        RegistrarAuditoria::guardarActualizacion($tool->fill(['activo' => false, 'observaciones' => 'Baja de inventario']));
        RegistrarAuditoria::guardarActualizacion($other->fill(['activo' => false]));

        Livewire::actingAs($user)->test(Index::class)
            ->call('cambiarEstado', $tool->id)
            ->call('verHerramienta', $tool->id)
            ->assertViewHas('movimientosHerramienta', fn ($movimientos): bool => $movimientos->total() === 11 && $movimientos->pluck('tipo')->all() === [
                'activacion', 'desactivacion', 'actualizacion', 'devolucion', 'devolucion', 'prestamo', 'prestamo', 'salida', 'entrada', 'entrada',
            ])
            ->assertSee('También se actualizaron datos de la herramienta.')
            ->assertSee('Entrega registrada')->assertSee('Existencia anterior')->assertSee('Existencia posterior')
            ->call('nextPage', 'movimientosHerramienta')->assertSet('paginators.page', 1)
            ->assertViewHas('movimientosHerramienta', fn ($movimientos): bool => $movimientos->pluck('tipo')->all() === ['apertura'])
            ->call('cerrarDetalle')->call('verHerramienta', $tool->id)->assertSet('paginators.movimientosHerramienta', 1);
    }

    public function test_quick_unit_creation_selects_the_unit_without_losing_the_tool_form(): void
    {
        $component = Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->call('crearHerramienta')->set('nombre', 'Cable de red')->set('tipoControl', 'cantidad')
            ->call('abrirModalCatalogo', 'unidad_medida')->assertSee('Permite cantidades decimales')
            ->set('nuevoNombreCatalogo', 'Metro de cable')->set('nuevoNombrePluralUnidad', 'Metros de cable')
            ->set('nuevaAbreviaturaUnidad', 'm')->set('nuevoPermiteDecimalesUnidad', true)
            ->call('guardarCatalogoRapido')->assertHasNoErrors()->assertSet('mostrarModalCatalogo', false)
            ->assertSet('mostrarModal', true)->assertSet('nombre', 'Cable de red')->assertSet('tipoControl', 'cantidad');

        $unit = UnidadMedida::query()->where('nombre_singular', 'Metro de cable')->firstOrFail();
        $this->assertTrue($unit->permite_decimales);
        $component->assertSet('unidadMedidaId', (string) $unit->id);
    }

    public function test_filters_search_and_clear_preserve_pagination_behavior(): void
    {
        $match = Herramienta::factory()->create(['nombre' => 'Match', 'tipo_control' => 'individual', 'cantidad_actual' => 1]);
        Herramienta::factory()->create(['nombre' => 'Other', 'activo' => false]);

        Livewire::actingAs(User::factory()->create(['activo' => true]))->test(Index::class)
            ->set('buscar', 'Match')->set('filtroTipo', 'individual')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="herramientas-filters"', false)
            ->assertSee('Filtros, 2 activos')->assertSee('Tipo de control: Individual')->assertSee('Estado: Activos')
            ->assertSee('Mostrando 1 de 1 herramientas encontradas')->assertSee('Página 1 de 1')
            ->assertSee('wire:click="previousPage" disabled', false)->assertSee('wire:click="nextPage" disabled', false)
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $match->id)
            ->call('setPage', 2)->call('removeFilter', 'filtroTipo')->assertSet('paginators.page', 1)
            ->assertSet('buscar', 'Match')->assertSet('filtroTipo', '')->assertSet('filtroEstado', 'activo')
            ->assertDontSee('herramientas-filter-chip-filtroTipo', false)->assertSee('Estado: Activos')
            ->call('setPage', 2)->set('filtroEstado', 'inactivo')->assertSet('paginators.page', 1)
            ->assertViewHas('herramientas', fn ($rows): bool => $rows->total() === 0)
            ->assertSee('0 resultados')->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertSet('buscar', '')->assertSet('filtroTipo', '')->assertSet('filtroEstado', '')
            ->assertDontSee('herramientas-filter-chip-', false)
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

    private function loan(Herramienta $tool, float $quantity, float $returned): PrestamoDetalle
    {
        $department = Departamento::create(['nombre' => 'Department '.$tool->id, 'creado_por' => $tool->creado_por]);
        $loan = Prestamo::create([
            'departamento_destino_id' => $department->id, 'persona_recibe' => 'Test Recipient',
            'fecha_prestamo' => now(), 'fecha_prevista_devolucion' => now()->addDay(), 'registrado_por' => $tool->creado_por,
        ]);

        return PrestamoDetalle::create([
            'prestamo_id' => $loan->id, 'herramienta_id' => $tool->id,
            'cantidad_prestada' => $quantity, 'cantidad_devuelta' => $returned, 'estado' => 'prestado',
        ]);
    }
}
