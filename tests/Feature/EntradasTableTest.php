<?php

namespace Tests\Feature;

use App\Livewire\Movimientos\Entradas\Index;
use App\Livewire\Movimientos\Salidas\Index as SalidasIndex;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Tinta;
use App\Models\User;
use App\Services\EntradasTable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EntradasTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_article_search_and_type_match_on_the_same_detail_and_keep_the_whole_entry(): void
    {
        $user = User::factory()->create(['name' => 'Ana Responsable', 'activo' => true]);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro', 'codigo_interno' => 'HT-427']);
        $ink = Tinta::factory()->create(['nombre' => 'Botella Epson', 'color' => 'Cian']);
        $entry = $this->entry($user, '2026-11-02 23:59:59', [['herramienta', $tool->id], ['tinta', $ink->id]], 'Pedido central');

        Livewire::actingAs($user)->test(Index::class)
            ->set('buscar', 'HT-427')->assertSee('Entrada #'.$entry->id)->assertSee('Botella Epson — Cian')
            ->set('tipoArticulo', 'tinta')->assertSee('0 resultados')
            ->set('buscar', 'Cian')->assertSee('Entrada #'.$entry->id)->assertSee('Taladro')
            ->set('buscar', 'Pedido central')->assertSee('Entrada #'.$entry->id)
            ->set('buscar', 'Ana Responsable')->assertSee('Entrada #'.$entry->id)
            ->set('desde', '2026-11-02')->set('hasta', '2026-11-02')->assertSee('Página 1 de 1')
            ->set('desde', '2026-11-03')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('removeFilter', 'desde')->assertSee('Página 1 de 1')->assertSee('Tipo de artículo: Tinta')
            ->call('clearFilters')->assertSet('buscar', 'Ana Responsable')->assertDontSee('entradas-filter-chip-', false);
    }

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    #[TestWith(['print'])]
    public function test_export_includes_every_matching_entry_and_its_complete_details(string $format): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $item = Insumo::factory()->create(['nombre' => 'Papel carta']);
        $first = $this->entry($user, '2026-11-01 00:00:00', [['insumo', $item->id], ['tinta', 999999]], 'Inicio de mes');
        for ($index = 0; $index < 10; $index++) {
            $last = $this->entry($user, '2026-11-02 23:59:59', [['insumo', $item->id]], 'Fin del rango');
        }
        $this->entry($user, '2026-11-03 00:00:00', [['insumo', $item->id]], 'Fuera del rango');

        $response = $this->get(route('movimientos.entradas.exportar', [
            'buscar' => 'Papel carta', 'tipoArticulo' => 'insumo', 'desde' => '2026-11-01', 'hasta' => '2026-11-02',
            'format' => $format, 'columns' => array_keys(EntradasTable::COLUMNS),
        ]))->assertOk();

        if ($format === 'print') {
            $response->assertSeeInOrder(['Fin del rango', 'Inicio de mes', 'Artículo no disponible'])->assertDontSee('Fuera del rango');

            return;
        }

        $rows = $format === 'xlsx' ? $this->spreadsheetRows($response->streamedContent())
            : array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim($response->streamedContent())));
        $this->assertCount(13, $rows);
        $this->assertSame((string) $last->id, (string) $rows[1][0]);
        $this->assertSame((string) $first->id, (string) $rows[12][0]);
        $this->assertSame('Artículo no disponible', $rows[12][4]);
    }

    public function test_pagination_stays_visible_and_resets_when_the_search_changes(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $item = Insumo::factory()->create(['nombre' => 'Papel carta']);
        for ($index = 0; $index < 11; $index++) {
            $this->entry($user, '2026-11-01 00:00:00', [['insumo', $item->id]]);
        }

        Livewire::actingAs($user)->test(Index::class)
            ->assertSee('Mostrando 10 de 11 entradas encontradas')->call('nextPage')->assertSee('Página 2 de 2')
            ->set('buscar', 'No hay coincidencia')->assertSet('paginators.page', 1)->assertSee('0 resultados')
            ->assertSee('wire:click="previousPage" disabled', false)->assertSee('wire:click="nextPage" disabled', false);
    }

    public function test_invalid_export_range_is_rejected_by_the_server(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->getJson(route('movimientos.entradas.exportar', ['desde' => '2026-11-02', 'hasta' => '2026-11-01', 'columns' => ['entrada']]))
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
    }

    public function test_new_entry_still_updates_inventory_and_appears_in_the_list(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $item = Insumo::factory()->create(['cantidad_actual' => 10, 'nombre' => 'Papel carta']);

        Livewire::actingAs($user)->test(Index::class)->call('nuevaEntrada')
            ->set('detalles.0.tipo_item', 'insumo')->set('detalles.0.item_id', (string) $item->id)
            ->set('detalles.0.cantidad', '3')->call('guardarEntrada')
            ->assertHasNoErrors()->assertSee('Entrada de inventario registrada correctamente.')
            ->assertSee('Papel carta')->assertSee('Página 1 de 1');

        $this->assertDatabaseHas('insumos', ['id' => $item->id, 'cantidad_actual' => 13]);
    }

    #[TestWith(['entradas', 'entrada', Index::class])]
    #[TestWith(['salidas', 'salida', SalidasIndex::class])]
    public function test_movement_order_applies_to_pagination_and_export(string $module, string $type, string $component): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $item = Insumo::factory()->create();
        $items = [['insumo', $item->id]];
        $newest = $this->entry($user, '2026-11-02 00:00:00', $items, 'Registro reciente', $type);
        $oldest = $this->entry($user, '2026-11-01 00:00:00', $items, 'Primer registro antiguo', $type);
        for ($index = 0; $index < 9; $index++) {
            $this->entry($user, '2026-11-01 00:00:00', $items, 'Otro registro antiguo', $type);
        }

        Livewire::actingAs($user)->test($component)
            ->assertViewHas('movimientos', fn ($rows): bool => $rows->first()->id === $newest->id)
            ->call('nextPage')->set('orden', 'antiguos')->assertSet('paginators.page', 1)
            ->assertViewHas('movimientos', fn ($rows): bool => $rows->first()->id === $oldest->id)
            ->assertSee('Orden: Más antiguos')->assertSee('name="orden" value="antiguos"', false)
            ->call('removeFilter', 'orden')->assertSet('orden', 'recientes');

        $this->get(route("movimientos.$module.exportar", ['format' => 'print', 'orden' => 'antiguos', 'columns' => ['observaciones']]))
            ->assertOk()->assertSeeInOrder(['Primer registro antiguo', 'Otro registro antiguo', 'Registro reciente']);
    }

    private function entry(User $user, string $date, array $items, ?string $notes = null, string $type = 'entrada'): MovimientoInventario
    {
        $entry = MovimientoInventario::create(['tipo' => $type, 'registrado_por' => $user->id, 'fecha_movimiento' => $date, 'observaciones' => $notes]);
        foreach ($items as [$type, $id]) {
            $entry->detalles()->create(['tipo_item' => $type, 'item_id' => $id, 'cantidad' => 2, 'existencia_anterior' => 1, 'existencia_posterior' => 3]);
        }

        return $entry;
    }

    private function spreadsheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'entradas-');
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
