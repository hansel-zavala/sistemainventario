<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Usuarios\Index as UsuariosIndex;
use App\Livewire\Catalogos\Categorias\Index as CategoriasIndex;
use App\Livewire\Catalogos\Departamentos\Index as DepartamentosIndex;
use App\Livewire\Catalogos\Marcas\Index as MarcasIndex;
use App\Livewire\Catalogos\TiposEquipo\Index as TiposEquipoIndex;
use App\Livewire\Catalogos\UnidadesMedida\Index as UnidadesMedidaIndex;
use App\Livewire\Inventario\Tintas\Index as TintasIndex;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\Prestamo;
use App\Models\PrestamoDetalle;
use App\Models\Tinta;
use App\Models\TipoEquipo;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    #[TestWith(['print'])]
    public function test_administrator_exports_only_selected_user_data(string $format): void
    {
        $adminRole = Role::create(['name' => 'Administrador', 'guard_name' => 'web']);
        $techRole = Role::create(['name' => 'Técnico', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole($adminRole);
        $this->login($admin);
        $tech = User::factory()->create(['name' => 'Operator', 'email' => 'match@example.test', 'activo' => true, 'created_at' => '2026-01-31 23:59:59']);
        $tech->assignRole($techRole);
        $outside = User::factory()->create(['name' => 'Outside', 'email' => 'match-outside@example.test', 'activo' => true, 'created_at' => '2026-02-01']);
        $outside->assignRole($techRole);
        User::factory()->create(['name' => 'Wrong role', 'email' => 'match-role@example.test', 'activo' => true, 'created_at' => '2026-01-15'])->assignRole($adminRole);
        User::factory()->create(['name' => 'Inactive', 'email' => 'match-inactive@example.test', 'activo' => false, 'created_at' => '2026-01-15'])->assignRole($techRole);

        $response = $this->get(route('administracion.usuarios.exportar', [
            'format' => $format, 'columns' => ['name', 'rol'], 'buscar' => 'match',
            'rol' => 'Técnico', 'estado' => 'activo', 'hasta' => '2026-01-31',
        ]))->assertOk();

        if ($format === 'print') {
            $response->assertSee('Operator')->assertSee('Técnico')->assertDontSee('Outside')->assertDontSee('Wrong role')
                ->assertDontSee('Inactive')->assertDontSee('match@example.test')->assertDontSee($tech->password);
        } elseif ($format === 'xlsx') {
            $this->assertSame([['Usuario', 'Rol'], ['Operator', 'Técnico']], $this->readXlsx($response->streamedContent()));
        } else {
            $this->assertSame("\xEF\xBB\xBFUsuario,Rol\nOperator,Técnico\n", str_replace("\r\n", "\n", $response->streamedContent()));
        }
    }

    public function test_user_exports_require_an_administrator(): void
    {
        $url = route('administracion.usuarios.exportar', ['columns' => ['name']]);
        $this->get($url)->assertRedirect(route('login'));
        $tech = User::factory()->create(['activo' => true]);
        $tech->assignRole(Role::create(['name' => 'Técnico', 'guard_name' => 'web']));
        $this->post(route('login.store'), ['email' => $tech->email, 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertForbidden();
    }

    public function test_user_exports_reject_sensitive_columns(): void
    {
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole(Role::create(['name' => 'Administrador', 'guard_name' => 'web']));
        $this->login($admin);

        $this->getJson(route('administracion.usuarios.exportar', ['columns' => ['password', 'remember_token']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['columns.0', 'columns.1']);
    }

    public function test_users_table_sorts_by_role_and_hides_unselected_export_columns(): void
    {
        $techRole = Role::create(['name' => 'Técnico', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Administrador', 'guard_name' => 'web']);
        $tech = User::factory()->create(['name' => 'Alpha', 'activo' => true]);
        $tech->assignRole($techRole);
        $admin = User::factory()->create(['name' => 'Zulu', 'activo' => true]);
        $admin->assignRole($adminRole);

        Livewire::actingAs($admin)->test(UsuariosIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->call('sortBy', 'rol')
            ->assertViewHas('usuarios', fn ($rows): bool => $rows->pluck('id')->all() === [$admin->id, $tech->id])
            ->call('setVisibleColumns', ['name'])
            ->assertDontSee('usuarios-heading-email', false)
            ->assertDontSee('usuarios-export-column-email', false)
            ->assertSee('Editar usuario Zulu');
    }

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
    #[TestWith(['tintas', 'csv'])]
    #[TestWith(['tintas', 'xlsx'])]
    #[TestWith(['tintas', 'print'])]
    #[TestWith(['categorias', 'csv'])]
    #[TestWith(['categorias', 'xlsx'])]
    #[TestWith(['categorias', 'print'])]
    #[TestWith(['tipos-equipo', 'csv'])]
    #[TestWith(['tipos-equipo', 'xlsx'])]
    #[TestWith(['tipos-equipo', 'print'])]
    #[TestWith(['marcas', 'csv'])]
    #[TestWith(['marcas', 'xlsx'])]
    #[TestWith(['marcas', 'print'])]
    #[TestWith(['departamentos', 'csv'])]
    #[TestWith(['departamentos', 'xlsx'])]
    #[TestWith(['departamentos', 'print'])]
    #[TestWith(['unidades-medida', 'csv'])]
    #[TestWith(['unidades-medida', 'xlsx'])]
    #[TestWith(['unidades-medida', 'print'])]
    public function test_exports_include_all_matching_pages_dates_columns_and_order(string $module, string $format): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        $nameColumn = $module === 'unidades-medida' ? 'nombre_singular' : 'nombre';
        for ($i = 0; $i < 12; $i++) {
            $this->item($module, [$nameColumn => sprintf('MATCH-%02d', $i), 'created_at' => $i === 0 ? '2026-01-01 00:00:00' : '2026-01-31 23:59:59']);
        }
        $this->item($module, [$nameColumn => 'MATCH-OUTSIDE', 'created_at' => '2026-02-01 00:00:00']);
        $this->item($module, [$nameColumn => 'MATCH-INACTIVE', 'activo' => false, 'created_at' => '2026-01-15']);
        $this->item($module, [$nameColumn => 'OTHER', 'created_at' => '2026-01-15']);
        if ($module === 'herramientas') {
            $this->item($module, ['nombre' => 'MATCH-WRONG-TYPE', 'tipo_control' => 'individual', 'created_at' => '2026-01-15']);
        }
        if ($module === 'tintas') {
            $this->item($module, ['nombre' => 'MATCH-WRONG-COLOR', 'color' => 'Cian', 'created_at' => '2026-01-15']);
        }
        if ($module === 'categorias') {
            $this->item($module, ['nombre' => 'MATCH-WRONG-TYPE', 'aplica_a' => 'insumo', 'created_at' => '2026-01-15']);
        }

        $prefix = in_array($module, ['categorias', 'tipos-equipo', 'marcas', 'departamentos', 'unidades-medida'], true) ? 'catalogos' : 'inventario';
        $route = $prefix.'.'.$module.'.exportar';
        $response = $this->get(route($route, [
            'format' => $format, 'columns' => [$nameColumn], 'buscar' => 'MATCH',
            'filtroEstado' => 'activo', ...($module === 'herramientas' ? ['filtroTipo' => 'cantidad'] : []),
            ...($module === 'tintas' ? ['filtroColor' => 'Negro'] : []),
            ...($module === 'categorias' ? ['filtroTipo' => 'equipo'] : []),
            'desde' => '2026-01-01', 'hasta' => '2026-01-31', 'sortColumn' => $nameColumn, 'sortDirection' => 'desc',
        ]))->assertOk();
        if ($format === 'print') {
            $response->assertSeeInOrder(['MATCH-11', 'MATCH-00'])->assertDontSee('MATCH-OUTSIDE')
                ->assertDontSee('MATCH-INACTIVE')->assertDontSee('MATCH-WRONG-TYPE')->assertDontSee('OTHER')
                ->assertDontSee('MATCH-WRONG-COLOR')
                ->assertDontSee('Acciones')->assertDontSee('Creado por');
        } elseif ($format === 'csv') {
            $content = $response->streamedContent();
            $lines = explode("\n", trim($content));
            $this->assertCount(13, $lines);
            $this->assertSame('MATCH-11', trim($lines[1]));
            $this->assertSame('MATCH-00', trim($lines[12]));
        } else {
            $rows = $this->readXlsx($response->streamedContent());
            $this->assertCount(13, $rows);
            $header = match ($module) {
                'herramientas' => 'Herramienta',
                'insumos' => 'Insumo',
                'tintas' => 'Tinta',
                'categorias' => 'Categoría',
                'tipos-equipo' => 'Tipo de equipo',
                'marcas' => 'Marca',
                'departamentos' => 'Departamento',
                'unidades-medida' => 'Singular',
            };
            $this->assertSame([$header], $rows[0]);
            $this->assertSame(['MATCH-11'], $rows[1]);
            $this->assertSame(['MATCH-00'], $rows[12]);
        }
    }

    public function test_category_filter_labels_and_pagination_stay_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        Categoria::factory()->create(['nombre' => 'Redes', 'aplica_a' => 'equipo']);

        Livewire::actingAs($user)->test(CategoriasIndex::class)
            ->set('buscar', 'Redes')->set('filtroTipo', 'equipo')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="categorias-filters"', false)
            ->assertSee('Filtros, 2 activos')->assertSee('Aplica a: Equipo')->assertSee('Estado: Activas')
            ->assertSee('Página 1 de 1')->assertSee('wire:click="previousPage" disabled', false)
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('setPage', 2)->call('removeFilter', 'filtroTipo')
            ->assertSet('paginators.page', 1)->assertSet('buscar', 'Redes')->assertSet('filtroEstado', 'activo')
            ->assertDontSee('categorias-filter-chip-filtroTipo', false)->assertSee('Filtros, 1 activo')
            ->set('filtroEstado', 'inactivo')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertDontSee('categorias-filter-chip-', false)->assertSee('Página 1 de 1');
    }

    public function test_category_table_sorts_by_creator_name_and_hides_unselected_columns(): void
    {
        $zulu = User::factory()->create(['name' => 'Zulu', 'activo' => true]);
        $alpha = User::factory()->create(['name' => 'Alpha']);
        $last = Categoria::factory()->create(['creado_por' => $zulu->id]);
        $first = Categoria::factory()->create(['creado_por' => $alpha->id]);

        Livewire::actingAs($zulu)->test(CategoriasIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->call('sortBy', 'creado_por')
            ->assertViewHas('categorias', fn ($rows): bool => $rows->pluck('id')->all() === [$first->id, $last->id])
            ->call('setVisibleColumns', ['nombre'])
            ->assertDontSee('categorias-heading-creado_por', false)
            ->assertDontSee('categorias-export-column-creado_por', false)
            ->assertSee('Acciones');
    }

    public function test_measurement_unit_status_filter_and_pagination_stay_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        UnidadMedida::factory()->create(['nombre_singular' => 'Litro', 'nombre_plural' => 'Litros', 'abreviatura' => 'ltr', 'activo' => true]);

        Livewire::actingAs($user)->test(UnidadesMedidaIndex::class)
            ->set('buscar', 'ltr')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="unidades-medida-filters"', false)
            ->assertSee('Filtros, 1 activo')->assertSee('Estado: Activas')
            ->assertSee('Página 1 de 1')->assertSee('wire:click="previousPage" disabled', false)
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('setPage', 2)->call('removeStatusFilter')
            ->assertSet('paginators.page', 1)->assertSet('buscar', 'ltr')->assertSet('filtroEstado', '')
            ->assertDontSee('unidades-medida-filter-chip-estado', false)
            ->set('filtroEstado', 'inactivo')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertDontSee('unidades-medida-filter-chip-estado', false)->assertSee('Página 1 de 1');
    }

    public function test_measurement_units_keep_plural_search_and_decimal_export_column(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        $unit = UnidadMedida::factory()->create(['nombre_singular' => 'Litro', 'nombre_plural' => 'Litros', 'abreviatura' => 'L', 'permite_decimales' => true]);
        UnidadMedida::factory()->create(['nombre_singular' => 'Caja', 'nombre_plural' => 'Cajas', 'abreviatura' => 'cj']);

        Livewire::actingAs($user)->test(UnidadesMedidaIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->set('buscar', 'Litros')
            ->assertViewHas('unidades', fn ($rows): bool => $rows->pluck('id')->all() === [$unit->id])
            ->call('setVisibleColumns', ['permite_decimales'])
            ->assertDontSee('unidades-medida-heading-nombre_plural', false)
            ->assertDontSee('unidades-medida-export-column-nombre_plural', false);

        $this->get(route('catalogos.unidades-medida.exportar', ['format' => 'print', 'columns' => ['permite_decimales'], 'buscar' => 'Litros']))
            ->assertOk()->assertSee('Decimales')->assertSee('<td>Sí</td>', false)->assertDontSee('Singular');
    }

    public function test_department_status_filter_and_pagination_stay_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        Departamento::factory()->create(['nombre' => 'Soporte', 'sigla' => 'TIC', 'activo' => true]);

        Livewire::actingAs($user)->test(DepartamentosIndex::class)
            ->set('buscar', 'TIC')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="departamentos-filters"', false)
            ->assertSee('Filtros, 1 activo')->assertSee('Estado: Activos')
            ->assertSee('Página 1 de 1')->assertSee('wire:click="previousPage" disabled', false)
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('setPage', 2)->call('removeStatusFilter')
            ->assertSet('paginators.page', 1)->assertSet('buscar', 'TIC')->assertSet('filtroEstado', '')
            ->assertDontSee('departamentos-filter-chip-estado', false)
            ->set('filtroEstado', 'inactivo')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertDontSee('departamentos-filter-chip-estado', false)->assertSee('Página 1 de 1');
    }

    public function test_departments_keep_the_initials_search_and_selected_export_columns(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->login($user);
        $department = Departamento::factory()->create(['nombre' => 'Soporte', 'sigla' => 'TIC']);
        Departamento::factory()->create(['nombre' => 'Contabilidad', 'sigla' => 'CON']);

        Livewire::actingAs($user)->test(DepartamentosIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->set('buscar', 'TIC')
            ->assertViewHas('departamentos', fn ($rows): bool => $rows->pluck('id')->all() === [$department->id])
            ->call('setVisibleColumns', ['sigla'])
            ->assertDontSee('departamentos-heading-nombre', false)
            ->assertDontSee('departamentos-export-column-nombre', false);

        $this->get(route('catalogos.departamentos.exportar', ['format' => 'print', 'columns' => ['sigla'], 'buscar' => 'TIC']))
            ->assertOk()->assertSee('Sigla')->assertSee('TIC')->assertDontSee('CON')->assertDontSee('Soporte');
    }

    public function test_brand_status_filter_and_pagination_stay_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        Marca::factory()->create(['nombre' => 'Dell', 'activo' => true]);

        Livewire::actingAs($user)->test(MarcasIndex::class)
            ->set('buscar', 'Dell')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="marcas-filters"', false)
            ->assertSee('Filtros, 1 activo')->assertSee('Estado: Activas')
            ->assertSee('Página 1 de 1')->assertSee('wire:click="previousPage" disabled', false)
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('setPage', 2)->call('removeStatusFilter')
            ->assertSet('paginators.page', 1)->assertSet('buscar', 'Dell')->assertSet('filtroEstado', '')
            ->assertDontSee('marcas-filter-chip-estado', false)
            ->set('filtroEstado', 'inactivo')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertDontSee('marcas-filter-chip-estado', false)->assertSee('Página 1 de 1');
    }

    public function test_brands_keep_column_selection_in_sync_with_the_export_form(): void
    {
        $user = User::factory()->create(['activo' => true]);
        Marca::factory()->create(['creado_por' => $user->id]);

        Livewire::actingAs($user)->test(MarcasIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->assertSee(route('catalogos.marcas.exportar'))
            ->call('setVisibleColumns', ['nombre'])
            ->assertDontSee('marcas-heading-creado_por', false)
            ->assertDontSee('marcas-export-column-creado_por', false)
            ->assertSee('Acciones');
    }

    public function test_equipment_type_status_filter_and_pagination_stay_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        TipoEquipo::factory()->create(['nombre' => 'Portátil', 'activo' => true]);

        Livewire::actingAs($user)->test(TiposEquipoIndex::class)
            ->set('buscar', 'Portátil')->set('filtroEstado', 'activo')
            ->assertSee('aria-controls="tipos-equipo-filters"', false)
            ->assertSee('Filtros, 1 activo')->assertSee('Estado: Activos')
            ->assertSee('Página 1 de 1')->assertSee('wire:click="previousPage" disabled', false)
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('setPage', 2)->call('removeStatusFilter')
            ->assertSet('paginators.page', 1)->assertSet('buscar', 'Portátil')->assertSet('filtroEstado', '')
            ->assertDontSee('tipos-equipo-filter-chip-estado', false)
            ->set('filtroEstado', 'inactivo')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('clearFilters')->assertDontSee('tipos-equipo-filter-chip-estado', false)->assertSee('Página 1 de 1');
    }

    public function test_equipment_types_sort_by_creator_and_export_only_visible_columns(): void
    {
        $zulu = User::factory()->create(['name' => 'Zulu', 'activo' => true]);
        $alpha = User::factory()->create(['name' => 'Alpha']);
        $last = TipoEquipo::factory()->create(['creado_por' => $zulu->id]);
        $first = TipoEquipo::factory()->create(['creado_por' => $alpha->id]);

        Livewire::actingAs($zulu)->test(TiposEquipoIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->call('sortBy', 'creado_por')
            ->assertViewHas('tiposEquipo', fn ($rows): bool => $rows->pluck('id')->all() === [$first->id, $last->id])
            ->call('setVisibleColumns', ['nombre'])
            ->assertDontSee('tipos-equipo-heading-creado_por', false)
            ->assertDontSee('tipos-equipo-export-column-creado_por', false);
    }

    public function test_ink_table_sorts_fractional_stock_and_keeps_export_columns_in_sync(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $small = Tinta::factory()->create(['botellas_completas' => 1, 'porcentaje_botella_abierta' => 25, 'stock_minimo_botellas' => 2]);
        $large = Tinta::factory()->create(['botellas_completas' => 1, 'porcentaje_botella_abierta' => 75]);
        Tinta::factory()->create(['color' => 'Cian']);

        Livewire::actingAs($user)->test(TintasIndex::class)
            ->assertSee('Columnas')->assertSee('Exportar')
            ->set('filtroColor', 'Negro')->call('sortBy', 'existencia')
            ->assertViewHas('tintas', fn ($rows): bool => $rows->pluck('id')->all() === [$small->id, $large->id])
            ->call('sortBy', 'existencia')
            ->assertViewHas('tintas', fn ($rows): bool => $rows->pluck('id')->all() === [$large->id, $small->id])
            ->call('setVisibleColumns', ['existencia'])
            ->assertSee('Stock bajo')->assertSee('1.25 botellas')
            ->assertDontSee('tintas-heading-presentacion', false)
            ->assertDontSee('tintas-export-column-presentacion', false);
    }

    public function test_ink_export_includes_open_bottle_percentage_and_low_stock(): void
    {
        $this->login(User::factory()->create(['activo' => true]));
        Tinta::factory()->create(['botellas_completas' => 1, 'porcentaje_botella_abierta' => 25, 'stock_minimo_botellas' => 2]);

        $this->get(route('inventario.tintas.exportar', ['format' => 'print', 'columns' => ['existencia']]))
            ->assertOk()->assertSee('1 botella completa + 25 % de una botella abierta')
            ->assertSee('Equivalente: 1.25 botellas / Stock bajo');
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
        return match ($module) {
            'herramientas' => Herramienta::factory()->create($attributes),
            'insumos' => Insumo::factory()->create($attributes),
            'tintas' => Tinta::factory()->create($attributes),
            'categorias' => Categoria::factory()->create($attributes),
            'tipos-equipo' => TipoEquipo::factory()->create($attributes),
            'marcas' => Marca::factory()->create($attributes),
            'departamentos' => Departamento::factory()->create($attributes),
            'unidades-medida' => UnidadMedida::factory()->create($attributes),
        };
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
