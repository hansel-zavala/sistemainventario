<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Equipos\Index;
use App\Livewire\Movimientos\Prestamos\Index as PrestamosIndex;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EquiposTableTest extends TestCase
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

    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_sort_cycles_and_applies_to_the_whole_result_with_stable_ties(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $first = Equipo::factory()->create(['numero_inventario' => 'AAA', 'created_at' => '2026-01-01']);
        $last = Equipo::factory()->create(['numero_inventario' => 'ZZZ', 'created_at' => '2026-02-01']);
        $component = Livewire::actingAs($user)->test(Index::class)
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $last->id)
            ->call('setPage', 2)->call('sortBy', 'numero_inventario')
            ->assertSet('paginators.page', 1)->assertSet('sortDirection', 'asc')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $first->id)
            ->call('sortBy', 'numero_inventario')->assertSet('sortDirection', 'desc')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $last->id)
            ->call('sortBy', 'numero_inventario')->assertSet('sortColumn', '')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $last->id);
        $first->update(['modelo' => 'Same', 'marca_id' => $last->marca_id]);
        $last->update(['modelo' => 'Same']);
        $component->call('sortBy', 'marca')->assertSet('sortDirection', 'asc')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $last->id);
    }

    public function test_related_names_and_model_are_sorted_instead_of_foreign_keys(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $z = Equipo::factory()->create(['modelo' => 'Z']);
        $a = Equipo::factory()->create(['modelo' => 'A']);
        foreach (['tipoEquipo', 'departamento', 'marca'] as $relation) {
            $z->$relation->update(['nombre' => 'Zulu']);
            $a->$relation->update(['nombre' => 'Alpha']);
        }
        foreach (['tipo_equipo', 'departamento', 'marca'] as $column) {
            Livewire::actingAs($user)->test(Index::class)->call('sortBy', $column)
                ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $a->id);
        }
        $z->update(['marca_id' => $a->marca_id]);
        Livewire::actingAs($user)->test(Index::class)->call('sortBy', 'marca')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->first()->id === $a->id);
    }

    public function test_column_selection_keeps_one_column_and_actions_visible(): void
    {
        $user = User::factory()->create(['activo' => true]);
        Livewire::actingAs($user)->test(Index::class)
            ->call('setVisibleColumns', ['numero_inventario'])
            ->call('toggleColumn', 'numero_inventario')
            ->assertSet('visibleColumns', ['numero_inventario'])
            ->assertSee('Acciones')->assertDontSee('heading-marca', false)
            ->call('toggleColumn', 'marca')->assertSet('visibleColumns', ['numero_inventario', 'marca']);
    }

    public function test_combined_filters_search_and_clear_reset_pagination(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $match = Equipo::factory()->create(['numero_inventario' => 'MATCH-001']);
        Equipo::factory()->create(['numero_inventario' => 'OTHER-001']);
        $component = Livewire::actingAs($user)->test(Index::class)
            ->set('buscar', 'MATCH')
            ->set('filters.categoria', (string) $match->categoria_id)
            ->set('filters.tipo', (string) $match->tipo_equipo_id)
            ->set('filters.marca', (string) $match->marca_id)
            ->set('filters.departamento', (string) $match->departamento_id)
            ->set('filters.estado', 'disponible')
            ->assertSee('aria-controls="equipos-filters"', false)
            ->assertSee('Filtros, 5 activos')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $match->id);
        $component->call('setPage', 2)->set('filters.estado', 'inactivo')->assertSet('paginators.page', 1)
            ->assertViewHas('equipos', fn ($rows): bool => $rows->total() === 0)
            ->call('clearFilters')->assertSet('buscar', '')
            ->assertDontSee('Filtros, 5 activos')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->total() === 2);
    }

    public function test_deactivation_and_reactivation_are_audited_and_inactive_rows_stay_visible(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $equipo = Equipo::factory()->create();
        $component = Livewire::actingAs($user)->test(Index::class)->call('cambiarEstado', $equipo->id)
            ->assertHasNoErrors()->assertSee('Inactivo');
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id, 'activo' => false]);
        $this->assertDatabaseHas('auditorias', ['modelo' => Equipo::class, 'registro_id' => $equipo->id, 'accion' => 'desactivacion', 'usuario_id' => $user->id]);
        $component->call('cambiarEstado', $equipo->id)->assertHasNoErrors()->assertSee('Disponible');
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id, 'activo' => true]);
        $this->assertDatabaseHas('auditorias', ['registro_id' => $equipo->id, 'accion' => 'activacion']);
    }

    public function test_pending_loan_prevents_deactivation_and_status_filter_matches_sorting(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $available = Equipo::factory()->create();
        $inactive = Equipo::factory()->create(['activo' => false]);
        $borrowed = Equipo::factory()->create();
        $this->loan($borrowed);
        Livewire::actingAs($user)->test(Index::class)->call('cambiarEstado', $borrowed->id)
            ->assertHasErrors('estadoEquipo')->assertSee('préstamo pendiente')
            ->call('sortBy', 'disponibilidad')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->pluck('id')->all() === [$available->id, $inactive->id, $borrowed->id])
            ->set('filters.estado', 'prestado')
            ->assertViewHas('equipos', fn ($rows): bool => $rows->total() === 1 && $rows->first()->id === $borrowed->id);
        $this->assertDatabaseHas('equipos', ['id' => $borrowed->id, 'activo' => true]);
        $this->assertDatabaseMissing('auditorias', ['registro_id' => $borrowed->id, 'accion' => 'desactivacion']);
    }

    public function test_inactive_equipment_cannot_be_borrowed_even_with_a_forged_selection(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $equipo = Equipo::factory()->create(['activo' => false]);
        Livewire::actingAs($user)->test(PrestamosIndex::class)
            ->assertViewHas('equipos', fn ($rows): bool => ! $rows->contains('id', $equipo->id))
            ->set('departamentoDestinoId', (string) $equipo->departamento_id)
            ->set('personaRecibe', 'Test Recipient')
            ->set('fechaPrevistaDevolucion', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('activosSeleccionados.0.tipo', 'equipo')
            ->set('activosSeleccionados.0.item_id', (string) $equipo->id)
            ->set('activosSeleccionados.0.cantidad', '1')
            ->call('guardarPrestamo')->assertHasErrors('activosSeleccionados.0.cantidad');
        $this->assertDatabaseCount('prestamos', 0);
    }

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    #[TestWith(['print'])]
    public function test_exports_include_all_matching_pages_and_inclusive_dates(string $format): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        $first = Equipo::factory()->create(['numero_inventario' => 'MATCH-00', 'created_at' => '2026-01-01 00:00:00']);
        for ($i = 1; $i <= 11; $i++) {
            Equipo::factory()->create([
                'numero_inventario' => sprintf('MATCH-%02d', $i), 'created_at' => '2026-01-31 23:59:59',
                'categoria_id' => $first->categoria_id, 'tipo_equipo_id' => $first->tipo_equipo_id,
                'marca_id' => $first->marca_id, 'departamento_id' => $first->departamento_id,
            ]);
        }
        Equipo::factory()->create(['numero_inventario' => 'OUTSIDE', 'created_at' => '2026-02-01']);
        $response = $this->get(route('inventario.equipos.exportar', [
            'format' => $format, 'columns' => ['numero_inventario'], 'buscar' => 'MATCH',
            'categoria' => $first->categoria_id, 'tipo' => $first->tipo_equipo_id,
            'marca' => $first->marca_id, 'departamento' => $first->departamento_id, 'estado' => 'disponible',
            'desde' => '2026-01-01', 'hasta' => '2026-01-31', 'sortColumn' => 'numero_inventario', 'sortDirection' => 'asc',
        ]))->assertOk();
        if ($format === 'print') {
            $response->assertSeeInOrder(['MATCH-00', 'MATCH-11'])->assertDontSee('OUTSIDE')->assertDontSee('Acciones');
        } elseif ($format === 'csv') {
            $content = $response->streamedContent();
            $this->assertStringContainsString('MATCH-00', $content);
            $this->assertStringContainsString('MATCH-11', $content);
            $this->assertStringNotContainsString('OUTSIDE', $content);
            $this->assertCount(13, array_filter(explode("\n", trim($content))));
        } else {
            $rows = $this->readXlsx($response->streamedContent());
            $this->assertCount(13, $rows);
            $this->assertSame(['Inventario'], $rows[0]);
            $this->assertSame(['MATCH-00'], $rows[1]);
            $this->assertSame(['MATCH-11'], $rows[12]);
        }
    }

    public function test_print_escapes_html_and_spreadsheets_do_not_execute_formulas(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        Equipo::factory()->create(['numero_inventario' => '=1+1', 'modelo' => '<script>alert(1)</script>']);
        $params = ['columns' => ['numero_inventario', 'marca']];
        $this->get(route('inventario.equipos.exportar', [...$params, 'format' => 'print']))
            ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
        $csv = $this->get(route('inventario.equipos.exportar', [...$params, 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $rows = $this->readXlsx($this->get(route('inventario.equipos.exportar', [...$params, 'format' => 'xlsx']))->streamedContent());
        $this->assertSame('=1+1', $rows[1][0]);
    }

    #[TestWith([['desde' => '2026-02-01', 'hasta' => '2026-01-01'], 'hasta'])]
    #[TestWith([['desde' => 'not-a-date'], 'desde'])]
    #[TestWith([['columns' => []], 'columns'])]
    #[TestWith([['columns' => ['secret']], 'columns.0'])]
    #[TestWith([['sortColumn' => 'id desc; drop table equipos'], 'sortColumn'])]
    #[TestWith([['sortDirection' => 'invalid'], 'sortDirection'])]
    #[TestWith([['estado' => 'invalid'], 'estado'])]
    #[TestWith([['marca' => 'invalid'], 'marca'])]
    #[TestWith([['format' => 'invalid'], 'format'])]
    public function test_export_rejects_invalid_inputs(array $input, string $error): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        $this->getJson(route('inventario.equipos.exportar', array_merge(['columns' => ['numero_inventario']], $input)))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    public function test_export_accepts_only_an_end_date_and_empty_results(): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        $this->get(route('inventario.equipos.exportar', ['columns' => ['numero_inventario'], 'hasta' => '2026-01-01', 'format' => 'print']))
            ->assertOk()->assertSee('No se encontraron equipos');
    }

    public function test_guests_and_inactive_users_cannot_export(): void
    {
        $url = route('inventario.equipos.exportar', ['columns' => ['numero_inventario']]);
        $this->get($url)->assertRedirect(route('login'));
        $user = User::factory()->create(['activo' => false]);
        $this->actingAs($user)->get($url)->assertRedirect(route('login'));
        Livewire::actingAs($user)->test(Index::class)->call('cambiarEstado', 1)->assertForbidden();
    }

    private function loan(Equipo $equipo): void
    {
        $loan = Prestamo::create([
            'departamento_destino_id' => $equipo->departamento_id, 'persona_recibe' => 'Test Recipient',
            'fecha_prestamo' => now(), 'fecha_prevista_devolucion' => now()->addDay(), 'registrado_por' => $equipo->creado_por,
        ]);
        PrestamoDetalle::create(['prestamo_id' => $loan->id, 'equipo_id' => $equipo->id, 'estado' => 'prestado']);
    }

    private function login(User $user): void
    {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    /** @return array<int, array<int, mixed>> */
    private function readXlsx(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'equipos-test-');
        file_put_contents($path, $content);
        $reader = new Reader;
        try {
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }

            return $rows;
        } finally {
            $reader->close();
            unlink($path);
        }
    }
}
