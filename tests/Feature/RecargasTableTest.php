<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Tintas\Recargas\Index;
use App\Models\Equipo;
use App\Models\RecargaTinta;
use App\Models\Tinta;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecargasTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_refill_filters_search_order_and_exports_stay_in_sync(): void
    {
        $user = User::factory()->create(['name' => 'Ana Técnica', 'activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $printer = Equipo::factory()->create(['numero_inventario' => 'IMP-42', 'activo' => false]);
        $black = Tinta::factory()->create(['nombre' => 'Referencia negra', 'color' => 'Negro', 'activo' => false]);
        $cyan = Tinta::factory()->create(['nombre' => 'Referencia cian', 'color' => 'Cian']);
        foreach (range(1, 11) as $day) {
            $refill = RecargaTinta::create(['equipo_id' => $printer->id, 'realizado_por' => $user->id, 'observaciones' => sprintf('Recarga del día %02d', $day)]);
            $refill->forceFill(['created_at' => sprintf('2026-11-%02d 23:59:59', $day)])->save();
            foreach ([$black, $cyan] as $ink) {
                $refill->detalles()->create(['tinta_id' => $ink->id, 'porcentaje_antes' => 10, 'porcentaje_agregado' => 20, 'porcentaje_despues' => 30]);
            }
        }
        $criteria = ['filtroEquipo' => (string) $printer->id, 'filtroColor' => 'Negro', 'filtroUsuario' => (string) $user->id,
            'desde' => '2026-11-01', 'hasta' => '2026-11-11'];

        Livewire::actingAs($user)->test(Index::class)->set('buscar', 'IMP-42')->set($criteria)
            ->assertSee('Mostrando 10 de 11 recargas encontradas')->assertSee('Referencia cian')
            ->call('nextPage')->assertSee('Página 2 de 2')->set('orden', 'antiguos')->assertSet('paginators.page', 1)
            ->assertSee('Recarga del día 01')->assertDontSee('Recarga del día 11')
            ->set('buscar', 'Referencia cian')->assertSee('0 resultados')
            ->assertSee('wire:click="nextPage" disabled', false)
            ->call('removeFilter', 'filtroColor')->assertSee('Mostrando 10 de 11 recargas encontradas')
            ->set('desde', '2026-11-12')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)
            ->call('clearFilters')->assertSet('buscar', 'Referencia cian')->assertSet('orden', 'recientes');

        $params = [...$criteria, 'orden' => 'antiguos', 'columns' => ['tinta', 'observaciones', 'agregado']];
        $this->get(route('inventario.tintas.recargas.exportar', [...$params, 'format' => 'print']))->assertOk()
            ->assertSeeInOrder(['Recarga del día 01', 'Recarga del día 11'])->assertSee('Referencia cian');
        $csv = $this->get(route('inventario.tintas.recargas.exportar', [...$params, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertCount(23, preg_split('/\r?\n/', trim($csv)));
        $xlsx = $this->get(route('inventario.tintas.recargas.exportar', [...$params, 'format' => 'xlsx']))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);
        $this->getJson(route('inventario.tintas.recargas.exportar', [...$params, 'desde' => '2026-11-12']))
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
    }

    public function test_refill_registration_still_consumes_ink_and_appears_in_the_list(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $printer = Equipo::factory()->create();
        $printer->tipoEquipo->update(['nombre' => 'Impresora']);
        $ink = Tinta::factory()->create(['botellas_completas' => 2, 'porcentaje_botella_abierta' => 0]);

        Livewire::actingAs($user)->test(Index::class)->call('nuevaRecarga')
            ->set('equipoId', (string) $printer->id)->set('detalles.0.tinta_id', (string) $ink->id)
            ->set('detalles.0.porcentaje_antes', '10')->set('detalles.0.porcentaje_agregado', '20')
            ->call('guardarRecarga')->assertHasNoErrors()->assertSet('mostrarModal', false)
            ->assertSee('Mostrando 1 de 1 recargas encontradas');

        $this->assertDatabaseHas('tintas', ['id' => $ink->id, 'botellas_completas' => 1, 'porcentaje_botella_abierta' => 80]);
    }
}
