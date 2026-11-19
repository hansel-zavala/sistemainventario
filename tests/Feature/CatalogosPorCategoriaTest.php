<?php

namespace Tests\Feature;

use App\Livewire\Catalogos\Categorias\Index as Categorias;
use App\Livewire\Catalogos\Marcas\Index as Marcas;
use App\Livewire\Catalogos\TiposEquipo\Index as TiposEquipo;
use App\Livewire\Inventario\Equipos\Index as Equipos;
use App\Livewire\Inventario\Herramientas\Index as Herramientas;
use App\Livewire\Inventario\Insumos\Index as Insumos;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Equipo;
use App\Models\Herramienta;
use App\Models\Insumo;
use App\Models\Marca;
use App\Models\TipoEquipo;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CatalogosPorCategoriaTest extends TestCase
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

    public function test_equipment_filters_types_and_brands_and_clears_only_incompatible_options(): void
    {
        $user = User::factory()->create();
        [$computo, $oficina] = Categoria::factory()->count(2)->create(['creado_por' => $user->id]);
        [$portatil, $impresora] = TipoEquipo::factory()->count(2)->create(['creado_por' => $user->id]);
        [$compartida, $inactiva] = Marca::factory()->count(2)->create(['creado_por' => $user->id]);
        $compartida->categorias()->attach([$computo->id, $oficina->id]);
        $inactiva->categorias()->attach($computo->id);
        $inactiva->update(['activo' => false]);
        $portatil->categorias()->attach($computo->id);
        $impresora->categorias()->attach($oficina->id);

        Livewire::actingAs($user)->test(Equipos::class)->call('crearEquipo')
            ->assertViewHas('categoriaDisponible', false)
            ->set('categoriaId', (string) $computo->id)
            ->assertViewHas('marcas', fn ($marcas): bool => $marcas->modelKeys() === [$compartida->id])
            ->assertViewHas('tiposEquipo', fn ($tipos): bool => $tipos->modelKeys() === [$portatil->id])
            ->set('marcaId', (string) $compartida->id)
            ->set('tipoEquipoId', (string) $portatil->id)
            ->set('categoriaId', (string) $oficina->id)
            ->assertSet('marcaId', (string) $compartida->id)
            ->assertSet('tipoEquipoId', '')
            ->assertViewHas('tiposEquipo', fn ($tipos): bool => $tipos->modelKeys() === [$impresora->id]);
    }

    #[TestWith([Herramientas::class, 'Herramienta', 'herramienta', 'herramientas', ['tipoControl' => 'cantidad']])]
    #[TestWith([Insumos::class, 'Insumo', 'insumo', 'insumos', []])]
    public function test_optional_brand_is_filtered_and_an_incompatible_choice_is_rejected(
        string $component, string $action, string $scope, string $table, array $fields,
    ): void {
        $user = User::factory()->create();
        [$first, $second] = Categoria::factory()->count(2)->create(['aplica_a' => $scope, 'creado_por' => $user->id]);
        $marca = Marca::factory()->create(['creado_por' => $user->id]);
        $marca->categorias()->attach($first->id);
        $unidad = UnidadMedida::factory()->create(['creado_por' => $user->id]);

        $form = Livewire::actingAs($user)->test($component)->call('crear'.$action)
            ->set($fields)
            ->set('categoriaId', (string) $first->id)
            ->assertViewHas('marcas', fn ($marcas): bool => $marcas->modelKeys() === [$marca->id])
            ->set('marcaId', (string) $marca->id)
            ->set('categoriaId', (string) $second->id)
            ->assertSet('marcaId', '')
            ->assertSee('No hay marcas activas vinculadas a esta categoría.')
            ->set('nombre', 'Artículo de prueba')
            ->set('unidadMedidaId', (string) $unidad->id);

        $form->set('marcaId', (string) $marca->id)->call('guardar'.$action)
            ->assertHasErrors('marcaId')
            ->assertSee('Seleccione una marca activa vinculada a la categoría.');
        $this->assertDatabaseCount($table, 0);

        $form->set('marcaId', '')->call('guardar'.$action)->assertHasNoErrors();
        $this->assertDatabaseHas($table, ['categoria_id' => $second->id, 'marca_id' => null]);
    }

    public function test_equipment_requires_active_links_and_accepts_the_configured_pair(): void
    {
        $user = User::factory()->create();
        $categoria = Categoria::factory()->create(['creado_por' => $user->id]);
        $wrongScope = Categoria::factory()->create(['aplica_a' => 'insumo', 'creado_por' => $user->id]);
        $tipo = TipoEquipo::factory()->create(['creado_por' => $user->id]);
        $marca = Marca::factory()->create(['creado_por' => $user->id, 'activo' => false]);
        $marca->categorias()->attach($categoria->id);
        $departamento = Departamento::factory()->create(['creado_por' => $user->id]);

        $form = Livewire::actingAs($user)->test(Equipos::class)->call('crearEquipo')
            ->set('categoriaId', (string) $wrongScope->id)
            ->set('departamentoId', (string) $departamento->id)
            ->set('numeroInventario', 'REL-0001')
            ->set('tipoEquipoId', (string) $tipo->id)
            ->set('marcaId', (string) $marca->id);

        $form->call('guardarEquipo')->assertHasErrors('categoriaId');
        $form->set('categoriaId', (string) $categoria->id)
            ->set('tipoEquipoId', (string) $tipo->id)
            ->set('marcaId', (string) $marca->id)
            ->call('guardarEquipo')->assertHasErrors('marcaId');
        $marca->update(['activo' => true]);
        $form->call('guardarEquipo')->assertHasErrors('tipoEquipoId');
        $this->assertDatabaseCount('equipos', 0);

        $tipo->categorias()->attach($categoria->id);
        $form->call('guardarEquipo')->assertHasNoErrors();
        $this->assertDatabaseHas('equipos', [
            'numero_inventario' => 'REL-0001', 'categoria_id' => $categoria->id,
            'tipo_equipo_id' => $tipo->id, 'marca_id' => $marca->id,
        ]);
    }

    #[TestWith([Equipos::class, 'Equipo', 'equipo', 'tipo_equipo', TipoEquipo::class, 'tipoEquipoId'])]
    #[TestWith([Equipos::class, 'Equipo', 'equipo', 'marca', Marca::class, 'marcaId'])]
    #[TestWith([Herramientas::class, 'Herramienta', 'herramienta', 'marca', Marca::class, 'marcaId'])]
    #[TestWith([Insumos::class, 'Insumo', 'insumo', 'marca', Marca::class, 'marcaId'])]
    public function test_quick_add_requires_a_category_and_links_the_new_option(
        string $component, string $action, string $scope, string $catalog, string $model, string $property,
    ): void {
        $user = User::factory()->create();
        $categoria = Categoria::factory()->create(['aplica_a' => $scope, 'creado_por' => $user->id]);
        $form = Livewire::actingAs($user)->test($component)->call('crear'.$action)
            ->call('abrirModalCatalogo', $catalog)
            ->set('nuevoNombreCatalogo', 'Catálogo rápido')
            ->call('guardarCatalogoRapido')
            ->assertHasErrors('nuevoNombreCatalogo');
        $this->assertSame(0, $model::count());

        $form->set('categoriaId', (string) $categoria->id)
            ->call('guardarCatalogoRapido')
            ->assertHasNoErrors();
        $item = $model::sole();
        $this->assertSame([$categoria->id], $item->categorias()->get()->modelKeys());
        $form->assertSet($property, (string) $item->id)
            ->call('abrirModalCatalogo', $catalog)
            ->set('nuevoNombreCatalogo', 'Catálogo rápido')
            ->call('guardarCatalogoRapido')
            ->assertHasErrors('nuevoNombreCatalogo')
            ->assertSee('Elija «Vincular existente»');
        $this->assertSame(1, $model::count());
    }

    #[TestWith([Equipos::class, 'Equipo', 'equipo', 'equipos'])]
    #[TestWith([Herramientas::class, 'Herramienta', 'herramienta', 'herramientas'])]
    #[TestWith([Insumos::class, 'Insumo', 'insumo', 'insumos'])]
    public function test_inventory_can_create_a_category_and_link_existing_options_without_losing_form_data(
        string $component, string $action, string $scope, string $table,
    ): void {
        $user = User::factory()->create();
        $otraCategoria = Categoria::factory()->create(['aplica_a' => $scope, 'creado_por' => $user->id]);
        $marca = Marca::factory()->create(['nombre' => 'Marca Zeta', 'creado_por' => $user->id]);
        $otraMarca = Marca::factory()->create(['nombre' => 'Marca Alfa', 'creado_por' => $user->id]);
        Marca::factory()->create(['activo' => false, 'creado_por' => $user->id]);
        $marca->categorias()->attach($otraCategoria->id);

        $form = Livewire::actingAs($user)->test($component)->call('crear'.$action)
            ->set('observaciones', 'Datos ingresados antes del alta rápida')
            ->call('abrirModalCatalogo', 'categoria')
            ->set('nuevoNombreCatalogo', 'Categoría rápida')
            ->call('guardarCatalogoRapido')
            ->assertHasNoErrors();

        $categoria = Categoria::where('nombre', 'Categoría rápida')->sole();
        $form->assertSet('categoriaId', (string) $categoria->id)
            ->call('abrirModalCatalogo', 'marca')
            ->assertSee('Categoría rápida')
            ->assertSee('Vincular existente')
            ->assertSee('Crear nuevo')
            ->set('vincularExistente', true)
            ->assertSee('Vincular y seleccionar')
            ->assertViewHas('opcionesCatalogoExistente', fn ($opciones): bool => $opciones->modelKeys() === [$otraMarca->id, $marca->id])
            ->set('catalogoExistenteId', (string) $marca->id)
            ->call('guardarCatalogoRapido')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCatalogo', false)
            ->assertSet('mostrarModal', true)
            ->assertSet('marcaId', (string) $marca->id);

        if ($scope === 'equipo') {
            $tipo = TipoEquipo::factory()->create(['creado_por' => $user->id]);
            $departamento = Departamento::factory()->create(['creado_por' => $user->id]);
            $form->call('abrirModalCatalogo', 'tipo_equipo')
                ->set('vincularExistente', true)
                ->set('catalogoExistenteId', (string) $tipo->id)
                ->call('guardarCatalogoRapido')
                ->assertHasNoErrors()
                ->assertSet('tipoEquipoId', (string) $tipo->id)
                ->set('departamentoId', (string) $departamento->id)
                ->set('numeroInventario', 'REL-RAPIDA');
            $this->assertDatabaseHas('categoria_tipo_equipo', ['categoria_id' => $categoria->id, 'tipo_equipo_id' => $tipo->id]);
        } else {
            $unidad = UnidadMedida::factory()->create(['creado_por' => $user->id]);
            $form->set('nombre', 'Artículo de catálogo rápido')
                ->set('unidadMedidaId', (string) $unidad->id);

            if ($scope === 'herramienta') {
                $form->set('tipoControl', 'cantidad');
            }
        }

        $form->call('guardar'.$action)->assertHasNoErrors();
        $this->assertDatabaseHas($table, [
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'observaciones' => 'Datos ingresados antes del alta rápida',
        ]);
        $this->assertDatabaseCount('marcas', 3);
        $this->assertSame([$otraCategoria->id, $categoria->id], $marca->categorias()->orderBy('categorias.id')->get()->modelKeys());
    }

    public function test_linking_an_existing_option_is_idempotent_and_requires_an_active_option_and_category(): void
    {
        $user = User::factory()->create();
        $categoria = Categoria::factory()->create(['creado_por' => $user->id]);
        $marca = Marca::factory()->create(['activo' => false, 'creado_por' => $user->id]);
        $form = Livewire::actingAs($user)->test(Equipos::class)
            ->call('crearEquipo')
            ->set('categoriaId', (string) $categoria->id)
            ->call('abrirModalCatalogo', 'marca')
            ->set('vincularExistente', true)
            ->set('catalogoExistenteId', (string) $marca->id)
            ->call('guardarCatalogoRapido')
            ->assertHasErrors('catalogoExistenteId');
        $this->assertDatabaseCount('categoria_marca', 0);

        $marca->update(['activo' => true]);
        $categoria->update(['activo' => false]);
        $form->call('guardarCatalogoRapido')
            ->assertHasErrors('catalogoExistenteId')
            ->assertSee('Seleccione una categoría activa');
        $this->assertDatabaseCount('categoria_marca', 0);

        $categoria->update(['activo' => true]);
        $form->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->call('abrirModalCatalogo', 'marca')
            ->assertSet('vincularExistente', false)
            ->assertSet('catalogoExistenteId', '')
            ->set('vincularExistente', true)
            ->set('catalogoExistenteId', (string) $marca->id)
            ->call('guardarCatalogoRapido')->assertHasNoErrors()
            ->assertSet('marcaId', (string) $marca->id);
        $this->assertDatabaseCount('categoria_marca', 1);
    }

    public function test_catalogs_assign_multiple_categories_to_one_item_and_limit_equipment_type_assignments(): void
    {
        $user = User::factory()->create();
        [$computo, $oficina] = Categoria::factory()->count(2)->create(['creado_por' => $user->id]);
        $herramienta = Categoria::factory()->create(['aplica_a' => 'herramienta', 'creado_por' => $user->id]);
        $insumo = Categoria::factory()->create(['aplica_a' => 'insumo', 'creado_por' => $user->id]);
        $marca = Marca::factory()->create(['creado_por' => $user->id]);
        $marca->categorias()->attach($oficina->id);

        Livewire::actingAs($user)->test(Marcas::class)
            ->call('editarMarca', $marca->id)
            ->assertSet('categoriaIds', [(string) $oficina->id])
            ->set('categoriaIds', [$computo->id, $herramienta->id, $insumo->id])
            ->call('guardarMarca')
            ->assertHasNoErrors();
        $this->assertSame([$computo->id, $herramienta->id, $insumo->id], $marca->categorias()->orderBy('categorias.id')->get()->modelKeys());
        $this->assertDatabaseCount('marcas', 1);

        $form = Livewire::actingAs($user)->test(TiposEquipo::class)
            ->call('crearTipoEquipo')
            ->set('nombre', 'Impresora')
            ->set('categoriaIds', [$computo->id, $herramienta->id])
            ->call('guardarTipoEquipo')
            ->assertHasErrors('categoriaIds');
        $this->assertDatabaseCount('tipos_equipo', 0);

        $form->set('categoriaIds', [$computo->id, $oficina->id])
            ->call('guardarTipoEquipo')->assertHasNoErrors();
        $tipo = TipoEquipo::sole();
        $this->assertSame([$computo->id, $oficina->id], $tipo->categorias()->orderBy('categorias.id')->get()->modelKeys());

        Livewire::actingAs($user)->test(Categorias::class)
            ->call('editarCategoria', $computo->id)
            ->set('aplicaA', 'insumo')
            ->call('guardarCategoria')->assertHasNoErrors();
        $this->assertSame([$oficina->id], $tipo->categorias()->get()->modelKeys());
        $this->assertDatabaseHas('categoria_marca', ['categoria_id' => $computo->id, 'marca_id' => $marca->id]);
    }

    public function test_migration_backfills_distinct_links_from_all_three_inventory_tables(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_11_19_130601_create_catalog_category_links.php');
        $migration->down();

        $marca = Marca::factory()->create(['creado_por' => $user->id]);
        $equipo = Equipo::factory()->create(['marca_id' => $marca->id, 'creado_por' => $user->id]);
        Equipo::factory()->create($equipo->only(['marca_id', 'categoria_id', 'tipo_equipo_id', 'creado_por']));
        $herramienta = Herramienta::factory()->create(['marca_id' => $marca->id, 'creado_por' => $user->id]);
        $insumo = Insumo::factory()->create(['marca_id' => $marca->id, 'creado_por' => $user->id]);
        Insumo::factory()->create(['categoria_id' => $insumo->categoria_id, 'creado_por' => $user->id]);

        $migration->up();

        $this->assertDatabaseCount('categoria_tipo_equipo', 1);
        $this->assertDatabaseHas('categoria_tipo_equipo', $equipo->only(['categoria_id', 'tipo_equipo_id']));
        $this->assertSame(
            [$equipo->categoria_id, $herramienta->categoria_id, $insumo->categoria_id],
            $marca->categorias()->orderBy('categorias.id')->get()->modelKeys(),
        );
        $this->assertDatabaseCount('equipos', 2);
        $this->assertDatabaseCount('insumos', 2);
    }

    #[TestWith([Equipos::class, Equipo::class, 'Equipo'])]
    #[TestWith([Herramientas::class, Herramienta::class, 'Herramienta'])]
    #[TestWith([Insumos::class, Insumo::class, 'Insumo'])]
    public function test_existing_assignments_can_be_kept_after_catalog_changes(
        string $component, string $model, string $action,
    ): void {
        $user = User::factory()->create();
        $marca = Marca::factory()->create(['creado_por' => $user->id, 'activo' => false]);
        $registro = $model::factory()->create(['marca_id' => $marca->id, 'creado_por' => $user->id]);
        $registro->categoria->update(['activo' => false]);

        Livewire::actingAs($user)->test($component)->call('editar'.$action, $registro->id)
            ->assertViewHas('marcas', fn ($marcas): bool => $marcas->modelKeys() === [$marca->id])
            ->assertSet('marcaId', (string) $marca->id)
            ->set('observaciones', 'Edición de registro existente')
            ->call('guardar'.$action)
            ->assertHasNoErrors();

        $this->assertDatabaseHas($registro->getTable(), [
            ...$registro->only(['id', 'marca_id', 'categoria_id']),
            'observaciones' => 'Edición de registro existente',
        ]);
    }
}
