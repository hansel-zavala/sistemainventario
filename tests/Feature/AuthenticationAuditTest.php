<?php

namespace Tests\Feature;

use App\Mail\PasswordRecoveryCode;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\RegistrarAuditoria;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_09_095145_create_permission_tables.php',
            'database/migrations/2026_09_09_095349_add_activo_to_users_table.php',
            'database/migrations/2026_10_19_152605_create_auditorias_table.php',
            'database/migrations/2026_10_27_120000_add_verification_fields_to_password_reset_tokens_table.php',
            'database/migrations/2026_10_28_235525_add_authentication_context_to_auditorias_table.php',
        ]];
    }

    public function test_successful_login_and_logout_are_audited_with_actor_target_and_ip(): void
    {
        $user = User::factory()->create(['activo' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
                'remember' => 'on',
            ])->assertRedirect(route('dashboard'));

        $login = Auditoria::where('accion', 'inicio_sesion_exitoso')->sole();
        $this->assertSame('autenticacion', $login->modelo);
        $this->assertSame($user->id, $login->registro_id);
        $this->assertSame($user->id, $login->usuario_id);
        $this->assertSame('203.0.113.10', $login->ip_address);
        $this->assertSame(['recordarme' => true], $login->valores_nuevos);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('logout'))->assertRedirect(route('login'));

        $logout = Auditoria::where('accion', 'cierre_sesion')->sole();
        $this->assertSame($user->id, $logout->registro_id);
        $this->assertSame($user->id, $logout->usuario_id);
        $this->assertSame('203.0.113.10', $logout->ip_address);
    }

    public function test_failed_and_blocked_logins_never_store_credentials_or_unknown_email(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $secret = 'NeverStoreThisPassword!';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => $secret])
                ->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $secret])
            ->assertRedirect(route('login'))->assertSessionHasErrors('throttle');

        $this->assertSame(5, Auditoria::where('accion', 'inicio_sesion_fallido')->count());
        $blocked = Auditoria::where('accion', 'bloqueo_login')->sole();
        $this->assertSame($user->id, $blocked->registro_id);
        $this->assertNull($blocked->usuario_id);

        $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => $secret])
            ->assertSessionHasErrors('email');
        $anonymous = Auditoria::where('accion', 'inicio_sesion_fallido')->latest('id')->firstOrFail();
        $this->assertNull($anonymous->registro_id);

        $serialized = Auditoria::query()->get()->toJson();
        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
        $this->assertStringNotContainsString('unknown@example.com', $serialized);
    }

    public function test_recovery_request_verification_resend_and_password_reset_are_audited_without_secrets(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'));
        $code = Mail::sent(PasswordRecoveryCode::class)->last()->code;
        $initial = Auditoria::where('accion', 'recuperacion_solicitada')->sole();
        $this->assertSame(['tipo' => 'solicitud_inicial'], $initial->valores_nuevos);
        $this->assertNull($initial->usuario_id);

        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'));
        $newCode = Mail::sent(PasswordRecoveryCode::class)->last()->code;
        $resend = Auditoria::where('accion', 'recuperacion_solicitada')->latest('id')->firstOrFail();
        $this->assertSame(['tipo' => 'reenvio'], $resend->valores_nuevos);

        $this->post(route('password.verify'), ['code' => $newCode])
            ->assertRedirect(route('password.reset'));
        $verified = Auditoria::where('accion', 'codigo_recuperacion_verificado')->sole();
        $this->assertSame($user->id, $verified->registro_id);
        $this->assertSame(['resultado' => 'verificado'], $verified->valores_nuevos);

        $password = 'NuevaClaveSegura!';
        $this->post(route('password.update'), [
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('login'));
        $reset = Auditoria::where('accion', 'contrasena_restablecida')->sole();
        $this->assertSame($user->id, $reset->registro_id);
        $this->assertSame(['origen' => 'recuperacion'], $reset->valores_nuevos);

        $serialized = Auditoria::query()->get()->toJson();
        $this->assertStringNotContainsString($code, $serialized);
        $this->assertStringNotContainsString($newCode, $serialized);
        $this->assertStringNotContainsString($password, $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
    }

    public function test_unknown_or_inactive_recovery_does_not_create_a_misleading_audit_record(): void
    {
        Mail::fake();
        $inactive = User::factory()->create(['activo' => false]);

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect(route('password.code'));
        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => $inactive->email])
            ->assertRedirect(route('password.code'));

        $this->assertDatabaseCount('auditorias', 0);
        Mail::assertNothingSent();
    }

    public function test_expired_session_is_audited_as_a_system_event(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        Auditoria::query()->delete();

        $this->travel(8)->hours();
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $expired = Auditoria::where('accion', 'sesion_vencida')->sole();
        $this->assertSame($user->id, $expired->registro_id);
        $this->assertNull($expired->usuario_id);
        $this->assertSame(['motivo' => 'tiempo_agotado'], $expired->valores_nuevos);
    }

    public function test_audit_screen_identifies_authentication_events_and_affected_users(): void
    {
        $administrator = User::factory()->create(['activo' => true]);
        Role::create(['name' => 'Administrador']);
        $administrator->assignRole('Administrador');
        $affected = User::factory()->create(['activo' => true]);
        Auditoria::create([
            'modelo' => 'autenticacion',
            'registro_id' => $affected->id,
            'accion' => 'inicio_sesion_fallido',
            'valores_nuevos' => ['resultado' => 'credenciales_invalidas'],
            'usuario_id' => null,
            'ip_address' => '203.0.113.20',
        ]);

        $this->actingAs($administrator)->withSession(['auth_window' => [
            'user_id' => $administrator->id,
            'expires_at' => now()->addHours(8)->timestamp,
            'password' => hash('sha256', $administrator->getAuthPassword()),
        ]])->get(route('administracion.auditorias'))
            ->assertOk()
            ->assertSee('Autenticación')
            ->assertSee('Inicio de sesión fallido')
            ->assertSee($affected->name)
            ->assertSee('Sistema / No autenticado');
    }

    public function test_authentication_audit_service_discards_sensitive_fields_defensively(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.30']);

        RegistrarAuditoria::eventoAutenticacion('inicio_sesion_fallido', $user, $request, [
            'resultado' => 'credenciales_invalidas',
            'password' => 'NeverStoreThis',
            'codigo_recuperacion' => '123456',
            'remember_token' => 'secret-token',
            'correo' => $user->email,
            'session_id' => 'secret-session',
        ]);

        $audit = Auditoria::sole();
        $this->assertSame(['resultado' => 'credenciales_invalidas'], $audit->valores_nuevos);
        $serialized = $audit->toJson();
        $this->assertStringNotContainsString('NeverStoreThis', $serialized);
        $this->assertStringNotContainsString('123456', $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
    }
}
