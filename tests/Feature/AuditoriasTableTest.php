<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Auditorias\Index;
use App\Models\Auditoria;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriasTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--realpath' => true, '--path' => array_values(array_filter(
            glob(database_path('migrations/*.php')),
            fn (string $path): bool => ! str_contains($path, '082647_upgrade_prestamos'),
        ))];
    }

    public function test_admin_can_filter_and_export_all_summary_rows_without_private_details(): void
    {
        $admin = User::factory()->create(['name' => 'Ana Responsable', 'activo' => true]);
        $admin->assignRole(Role::create(['name' => 'Administrador']));
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        foreach (range(101, 111) as $record) {
            Auditoria::create(['modelo' => Equipo::class, 'registro_id' => $record, 'accion' => 'creacion',
                'usuario_id' => $admin->id, 'valores_nuevos' => ['nota' => 'Dato exclusivo del detalle'], 'ip_address' => '203.0.113.40'])
                ->forceFill(['created_at' => '2026-11-05 23:59:59'])->save();
        }

        $criteria = ['filtroModulo' => Equipo::class, 'filtroAccion' => 'creacion', 'filtroUsuario' => (string) $admin->id,
            'desde' => '2026-11-05', 'hasta' => '2026-11-05'];
        Livewire::actingAs($admin)->test(Index::class)->set('buscar', 'Creación')->set($criteria)
            ->assertSee('Mostrando 10 de 11 registros encontrados')->assertDontSee('#101')
            ->call('nextPage')->assertSee('#101')->set('orden', 'antiguos')->assertSee('Página 1 de 2')
            ->assertSee('#101')->assertDontSee('#111')
            ->set('desde', '2026-11-06')->assertSee('La fecha final debe ser igual o posterior a la fecha inicial.')
            ->assertSee('disabled @click="menu = menu', false)->call('clearFilters')->assertSet('buscar', 'Creación');

        $params = [...$criteria, 'buscar' => '203.0.113.40', 'orden' => 'antiguos', 'columns' => ['registro', 'responsable', 'accion']];
        $this->get(route('administracion.auditorias.exportar', [...$params, 'format' => 'print']))->assertOk()
            ->assertSeeInOrder(['#101', '#111'])->assertSee('Ana Responsable')
            ->assertDontSee('Dato exclusivo del detalle')->assertDontSee('203.0.113.40');
        $csv = $this->get(route('administracion.auditorias.exportar', [...$params, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertCount(12, preg_split('/\r?\n/', trim($csv)));
        $xlsx = $this->get(route('administracion.auditorias.exportar', [...$params, 'format' => 'xlsx']))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);
        $this->getJson(route('administracion.auditorias.exportar', ['columns' => ['valores_nuevos']]))
            ->assertUnprocessable()->assertJsonValidationErrors('columns.0');
    }

    public function test_export_is_restricted_to_administrators(): void
    {
        $url = route('administracion.auditorias.exportar', ['columns' => ['fecha']]);
        $this->get($url)->assertRedirect(route('login'));
        $technician = User::factory()->create(['activo' => true]);
        $technician->assignRole(Role::create(['name' => 'Técnico']));

        $this->post(route('login.store'), ['email' => $technician->email, 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertForbidden();
    }
}
