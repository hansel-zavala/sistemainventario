<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InventoryExportTest extends TestCase
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

    #[TestWith(['herramientas', 'csv'])]
    #[TestWith(['herramientas', 'xlsx'])]
    #[TestWith(['herramientas', 'print'])]
    #[TestWith(['insumos', 'csv'])]
    #[TestWith(['insumos', 'xlsx'])]
    #[TestWith(['insumos', 'print'])]
    public function test_exports_include_all_matching_pages_dates_columns_and_order(string $module, string $format): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        for ($i = 0; $i < 12; $i++) {
            $this->item($module, ['nombre' => sprintf('MATCH-%02d', $i), 'created_at' => $i === 0 ? '2026-01-01 00:00:00' : '2026-01-31 23:59:59']);
        }
        $this->item($module, ['nombre' => 'MATCH-OUTSIDE', 'created_at' => '2026-02-01 00:00:00']);
        $this->item($module, ['nombre' => 'MATCH-INACTIVE', 'activo' => false, 'created_at' => '2026-01-15']);
        $this->item($module, ['nombre' => 'OTHER', 'created_at' => '2026-01-15']);
        if ($module === 'herramientas') {
            $this->item($module, ['nombre' => 'MATCH-WRONG-TYPE', 'tipo_control' => 'individual', 'created_at' => '2026-01-15']);
        }

        $response = $this->get(route('inventario.'.$module.'.exportar', [
            'format' => $format, 'columns' => ['nombre'], 'buscar' => 'MATCH',
            'filtroEstado' => 'activo', ...($module === 'herramientas' ? ['filtroTipo' => 'cantidad'] : []),
            'desde' => '2026-01-01', 'hasta' => '2026-01-31', 'sortColumn' => 'nombre', 'sortDirection' => 'desc',
        ]))->assertOk();
        if ($format === 'print') {
            $response->assertSeeInOrder(['MATCH-11', 'MATCH-00'])->assertDontSee('MATCH-OUTSIDE')
                ->assertDontSee('MATCH-INACTIVE')->assertDontSee('MATCH-WRONG-TYPE')->assertDontSee('OTHER')
                ->assertDontSee('Acciones')->assertDontSee('Categoría');
        } elseif ($format === 'csv') {
            $content = $response->streamedContent();
            $lines = explode("\n", trim($content));
            $this->assertCount(13, $lines);
            $this->assertSame('MATCH-11', trim($lines[1]));
            $this->assertSame('MATCH-00', trim($lines[12]));
        } else {
            $rows = $this->readXlsx($response->streamedContent());
            $this->assertCount(13, $rows);
            $this->assertSame([$module === 'herramientas' ? 'Herramienta' : 'Insumo'], $rows[0]);
            $this->assertSame(['MATCH-11'], $rows[1]);
            $this->assertSame(['MATCH-00'], $rows[12]);
        }
    }

    public function test_tool_exports_include_partial_returns_individual_loans_and_inactive_status(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        $tool = Herramienta::factory()->create(['cantidad_actual' => 10.5]);
        $individual = Herramienta::factory()->create(['tipo_control' => 'individual', 'cantidad_actual' => 1]);
        Herramienta::factory()->create(['activo' => false]);
        $department = Departamento::create(['nombre' => 'Test department', 'creado_por' => $user->id]);
        $loan = Prestamo::create([
            'departamento_destino_id' => $department->id, 'persona_recibe' => 'Test Recipient',
            'fecha_prestamo' => now(), 'fecha_prevista_devolucion' => now()->addDay(), 'registrado_por' => $user->id,
        ]);
        PrestamoDetalle::create(['prestamo_id' => $loan->id, 'herramienta_id' => $tool->id, 'cantidad_prestada' => 9, 'cantidad_devuelta' => 1, 'estado' => 'prestado']);
        PrestamoDetalle::create(['prestamo_id' => $loan->id, 'herramienta_id' => $individual->id, 'cantidad_prestada' => 1, 'cantidad_devuelta' => 0, 'estado' => 'prestado']);

        $this->get(route('inventario.herramientas.exportar', ['format' => 'print', 'columns' => ['disponibilidad']]))
            ->assertOk()->assertSee('2.50 disponibles / 8.00 prestadas')->assertSee('Prestada')->assertSee('Inactiva');
        $this->assertDatabaseHas('herramientas', ['id' => $tool->id, 'cantidad_actual' => 10.5]);
    }

    #[TestWith(['herramientas'])]
    #[TestWith(['insumos'])]
    public function test_spreadsheets_protect_formula_strings_and_print_escapes_html(string $module): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        $this->item($module, ['nombre' => '=1+1']);
        $this->item($module, ['nombre' => '<script>alert(1)</script>']);
        $params = ['columns' => ['nombre'], 'sortColumn' => 'nombre', 'sortDirection' => 'desc'];

        $this->get(route('inventario.'.$module.'.exportar', [...$params, 'format' => 'print']))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
        $csv = $this->get(route('inventario.'.$module.'.exportar', [...$params, 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $rows = $this->readXlsx($this->get(route('inventario.'.$module.'.exportar', [...$params, 'format' => 'xlsx']))->streamedContent());
        $this->assertSame('=1+1', $rows[1][0]);
    }

    public function test_tool_codes_retain_leading_zeroes_and_units_and_stock_alerts_are_exported(): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        $tool = Herramienta::factory()->create(['codigo_interno' => '000012', 'cantidad_actual' => 1, 'stock_minimo' => 2]);
        $tool->unidadMedida->update(['nombre_singular' => 'unidad', 'nombre_plural' => 'unidades']);
        $item = Insumo::factory()->create(['cantidad_actual' => 1, 'stock_minimo' => 2]);
        $item->unidadMedida->update(['nombre_singular' => 'caja', 'nombre_plural' => 'cajas']);

        $rows = $this->readXlsx($this->get(route('inventario.herramientas.exportar', ['columns' => ['codigo_interno', 'cantidad_actual'], 'format' => 'xlsx']))->streamedContent());
        $this->assertSame(['000012', '1 unidad / Stock bajo'], $rows[1]);
        $this->get(route('inventario.insumos.exportar', ['columns' => ['cantidad_actual', 'stock_minimo', 'marca'], 'format' => 'print']))
            ->assertOk()->assertSee('1 caja / Stock bajo')->assertSee('2 cajas')->assertSee('—');
    }

    #[DataProvider('invalidInputs')]
    public function test_export_rejects_invalid_inputs(string $module, array $input, string $error): void
    {
        $this->login(User::factory()->create(['activo' => true]));

        $this->getJson(route('inventario.'.$module.'.exportar', array_merge(['columns' => ['nombre']], $input)))
            ->assertUnprocessable()->assertJsonValidationErrors($error);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function invalidInputs(): array
    {
        $cases = [];
        foreach (['herramientas', 'insumos'] as $module) {
            foreach ([
                'range' => [['desde' => '2026-02-01', 'hasta' => '2026-01-01'], 'hasta'],
                'date' => [['desde' => 'not-a-date'], 'desde'],
                'columns' => [['columns' => []], 'columns'],
                'unknown-column' => [['columns' => ['secret']], 'columns.0'],
                'sort' => [['sortColumn' => 'id desc; drop table users'], 'sortColumn'],
                'direction' => [['sortDirection' => 'invalid'], 'sortDirection'],
                'state' => [['filtroEstado' => 'invalid'], 'filtroEstado'],
                'format' => [['format' => 'invalid'], 'format'],
            ] as $name => [$input, $error]) {
                $cases[$module.'-'.$name] = [$module, $input, $error];
            }
        }
        $cases['tool-type'] = ['herramientas', ['filtroTipo' => 'invalid'], 'filtroTipo'];

        return $cases;
    }

    #[TestWith(['herramientas'])]
    #[TestWith(['insumos'])]
    public function test_guests_and_inactive_users_cannot_export(string $module): void
    {
        $url = route('inventario.'.$module.'.exportar', ['columns' => ['nombre']]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['activo' => false]))->get($url)->assertRedirect(route('login'));
    }

    #[TestWith(['herramientas'])]
    #[TestWith(['insumos'])]
    public function test_only_an_end_date_and_empty_results_are_supported(string $module): void
    {
        $this->login(User::factory()->create(['activo' => true]));

        $this->get(route('inventario.'.$module.'.exportar', ['columns' => ['nombre'], 'desde' => '', 'hasta' => '2026-01-01', 'format' => 'print']))
            ->assertOk()->assertSee('No se encontraron registros');
    }

    /** @param array<string, mixed> $attributes */
    private function item(string $module, array $attributes): Model
    {
        return $module === 'herramientas'
            ? Herramienta::factory()->create($attributes)
            : Insumo::factory()->create($attributes);
    }

    private function login(User $user): void
    {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    /** @return array<int, array<int, mixed>> */
    private function readXlsx(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'inventory-test-');
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
