<?php

namespace Tests\Feature;

use App\Livewire\Movimientos\Salidas\Index;
use App\Models\Departamento;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Tinta;
use App\Models\User;
use App\Services\SalidasTable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SalidasTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_exit_filters_combine_destination_date_and_matching_article_type(): void
    {
        $user = User::factory()->create(['name' => 'Ana Responsable', 'activo' => true]);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro', 'codigo_interno' => 'HT-427']);
        $ink = Tinta::factory()->create(['nombre' => 'Botella Epson', 'color' => 'Cian']);
        $department = Departamento::factory()->create(['nombre' => 'Archivo', 'sigla' => 'AR', 'activo' => false]);
        $entry = $this->entry($user, '2026-11-02 23:59:59', [['herramienta', $tool->id], ['tinta', $ink->id]], 'Pedido central', $department->id);
        $withoutDestination = $this->entry($user, '2026-11-02 20:00:00', [['tinta', $ink->id]]);

        Livewire::actingAs($user)->test(Index::class)
            ->set('buscar', 'HT-427')->assertSee('Salida #'.$entry->id)->assertSee('Botella Epson — Cian')
            ->set('tipoArticulo', 'tinta')->assertSee('0 resultados')
            ->set('buscar', 'Cian')->assertSee('Salida #'.$entry->id)->assertSee('Taladro')
            ->set('buscar', 'Pedido central')->assertSee('Salida #'.$entry->id)
            ->set('buscar', 'Ana Responsable')->assertSee('Salida #'.$entry->id)
            ->set('destino', (string) $department->id)->assertSee('Departamento destino: Archivo (AR)')
            ->assertDontSee('Salida #'.$withoutDestination->id)
            ->set('desde', '2026-11-02')->set('hasta', '2026-11-02')->assertSee('Página 1 de 1')
            ->set('desde', '2026-11-03')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('removeFilter', 'desde')->assertSee('Página 1 de 1')->assertSee('Tipo de artículo: Tinta')
            ->set('destino', SalidasTable::NO_DESTINATION)->assertSee('Salida #'.$withoutDestination->id)->assertDontSee('Salida #'.$entry->id)
            ->call('clearFilters')->assertSet('buscar', 'Ana Responsable')->assertDontSee('salidas-filter-chip-', false);
    }

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    #[TestWith(['print'])]
    public function test_export_includes_every_matching_exit_and_its_complete_details(string $format): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $item = Insumo::factory()->create(['nombre' => 'Papel carta']);
        $department = Departamento::factory()->create(['nombre' => 'Archivo', 'sigla' => 'AR', 'activo' => false]);
        $first = $this->entry($user, '2026-11-01 00:00:00', [['insumo', $item->id], ['tinta', 999999]], 'Inicio de mes', $department->id);
        for ($index = 0; $index < 10; $index++) {
            $last = $this->entry($user, '2026-11-02 23:59:59', [['insumo', $item->id]], 'Fin del rango', $department->id);
        }
        $this->entry($user, '2026-11-03 00:00:00', [['insumo', $item->id]], 'Fuera del rango', $department->id);
        $this->entry($user, '2026-11-02 19:00:00', [['insumo', $item->id]], 'Otro destino');

        $response = $this->get(route('movimientos.salidas.exportar', [
            'buscar' => 'Papel carta', 'destino' => $department->id, 'tipoArticulo' => 'insumo', 'desde' => '2026-11-01', 'hasta' => '2026-11-02',
            'format' => $format, 'columns' => array_keys(SalidasTable::COLUMNS),
        ]))->assertOk();

        if ($format === 'print') {
            $response->assertSeeInOrder(['Fin del rango', 'Inicio de mes', 'Artículo no disponible'])
                ->assertSee('Archivo (AR)')->assertSee('Laura Receptora')->assertSee('Reposición')
                ->assertDontSee('Fuera del rango')->assertDontSee('Otro destino');

            return;
        }

        $rows = $format === 'xlsx' ? $this->spreadsheetRows($response->streamedContent())
            : array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim($response->streamedContent())));
        $this->assertCount(13, $rows);
        $this->assertSame((string) $last->id, (string) $rows[1][0]);
        $this->assertSame((string) $first->id, (string) $rows[12][0]);
        $this->assertSame(['Archivo (AR)', 'Laura Receptora', 'Reposición'], array_slice($rows[12], 3, 3));
        $this->assertSame('Artículo no disponible', $rows[12][7]);
    }

    public function test_pagination_stays_visible_and_resets_when_the_search_changes(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $item = Insumo::factory()->create(['nombre' => 'Papel carta']);
        for ($index = 0; $index < 11; $index++) {
            $this->entry($user, '2026-11-01 00:00:00', [['insumo', $item->id]]);
        }

        Livewire::actingAs($user)->test(Index::class)
            ->assertSee('Mostrando 10 de 11 salidas encontradas')->call('nextPage')->assertSee('Página 2 de 2')
            ->set('buscar', 'No hay coincidencia')->assertSet('paginators.page', 1)->assertSee('0 resultados')
            ->assertSee('wire:click="previousPage" disabled', false)->assertSee('wire:click="nextPage" disabled', false);
    }

    public function test_invalid_export_range_and_destination_are_rejected_by_the_server(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->getJson(route('movimientos.salidas.exportar', ['desde' => '2026-11-02', 'hasta' => '2026-11-01', 'destino' => 999999, 'columns' => ['salida']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['hasta', 'destino']);
    }

    public function test_new_exit_deducts_inventory_and_appears_in_the_list(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $item = Insumo::factory()->create(['cantidad_actual' => 10, 'nombre' => 'Papel carta']);

        $department = Departamento::factory()->create(['nombre' => 'Archivo', 'sigla' => 'AR']);

        Livewire::actingAs($user)->test(Index::class)->call('nuevaSalida')
            ->set('departamentoDestinoId', (string) $department->id)->set('personaRecibe', 'Laura')->set('motivo', 'Reposición')
            ->set('observaciones', 'Entrega confirmada.')
            ->set('detalles.0.tipo_item', 'insumo')->set('detalles.0.item_id', (string) $item->id)
            ->set('detalles.0.cantidad', '3')->call('guardarSalida')
            ->assertHasNoErrors()->assertSee('Salida de inventario registrada correctamente.')
            ->assertSeeHtmlInOrder([
                'Salida #', '1 artículo', 'Registrado por', e($user->name), 'Ver detalle', 'Ocultar',
                'Destino', 'Archivo (AR)', 'Recibe', 'Laura', 'Motivo', 'Reposición',
                'Papel carta', 'Insumo · 1 artículo registrado', '− 3', 'Anterior', 'Salida', 'Posterior',
                'Papel carta', 'Insumo', '10', '− 3', '7', 'Entrega confirmada.',
            ])->assertSee('Página 1 de 1');

        $this->assertDatabaseHas('insumos', ['id' => $item->id, 'cantidad_actual' => 7]);
    }

    public function test_exit_with_four_articles_previews_two_and_keeps_all_articles_in_the_detail(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $tool = Herramienta::factory()->create(['nombre' => 'Taladro de banco']);
        $ink = Tinta::factory()->create(['nombre' => 'Botella Epson', 'color' => 'Cian']);
        $supply = Insumo::factory()->create(['nombre' => 'Cinta de embalaje']);
        $exit = $this->entry($user, '2026-11-02 14:11:00', [
            ['herramienta', $tool->id],
            ['tinta', $ink->id],
            ['insumo', $supply->id],
            ['insumo', 999999],
        ]);

        $history = Livewire::actingAs($user)->test(Index::class);

        $history->assertSeeHtmlInOrder([
            "Salida #{$exit->id}", '4 artículos', '02/11/2026 · 14:11', 'Registrado por', e($user->name),
            'Ver detalle', 'Ocultar', 'Sin destino',
            'Taladro de banco', '− 2', 'Botella Epson — Cian', '− 2', 'Artículos registrados',
            'Y 2 artículos más…', 'Anterior', 'Salida', 'Posterior',
            'Taladro de banco', 'Botella Epson — Cian', 'Cinta de embalaje', 'Artículo no disponible',
        ])->assertDontSeeText('Ver registro completo');
        $this->assertSame(1, substr_count($history->html(true), 'Cinta de embalaje'));
        $this->assertSame(1, substr_count($history->html(true), 'Artículo no disponible'));
    }

    private function entry(User $user, string $date, array $items, ?string $notes = null, ?int $department = null): MovimientoInventario
    {
        $entry = MovimientoInventario::create(['tipo' => 'salida', 'registrado_por' => $user->id, 'fecha_movimiento' => $date, 'observaciones' => $notes, 'departamento_destino_id' => $department, 'persona_recibe' => 'Laura Receptora', 'motivo' => 'Reposición']);
        foreach ($items as [$type, $id]) {
            $entry->detalles()->create(['tipo_item' => $type, 'item_id' => $id, 'cantidad' => 2, 'existencia_anterior' => 1, 'existencia_posterior' => 3]);
        }

        return $entry;
    }

    private function spreadsheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'salidas-');
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
