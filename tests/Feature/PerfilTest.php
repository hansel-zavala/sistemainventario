<?php

namespace Tests\Feature;

use App\Mail\PasswordChanged;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerfilTest extends TestCase
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

    #[TestWith(['GET', 'perfil.show'])]
    #[TestWith(['PATCH', 'perfil.update'])]
    #[TestWith(['PUT', 'perfil.password.update'])]
    public function test_guests_are_redirected_to_login(string $method, string $route): void
    {
        $this->call($method, route($route))->assertRedirect(route('login'));
    }

    public function test_profile_displays_own_information_and_escapes_the_name(): void
    {
        $usuario = User::factory()->create(['activo' => true, 'name' => '<script>alert("name")</script>']);
        $this->login($usuario);

        $this->get(route('perfil.show'))->assertOk()->assertSee($usuario->name)
            ->assertDontSee($usuario->name, false)->assertSee($usuario->email)
            ->assertSee('Editar información')->assertSee('Cambiar contraseña')
            ->assertSee('contacta al administrador')->assertDontSee('name="email"', false)
            ->assertDontSee($usuario->password)->assertDontSee($usuario->remember_token);
    }

    #[TestWith(['Administrador', true])]
    #[TestWith(['Técnico', false])]
    public function test_navigation_respects_administration_role(string $role, bool $canAdminister): void
    {
        $usuario = User::factory()->create(['activo' => true]);
        Role::create(['name' => $role, 'guard_name' => 'web']);
        $usuario->assignRole($role);
        $this->login($usuario);

        $response = $this->get(route('perfil.show'))->assertOk()
            ->assertSee('Buscar módulo')->assertSee('Recargas de tinta')->assertSee('Cerrar sesión')
            ->assertSee('Las notificaciones estarán disponibles próximamente.');

        if ($canAdminister) {
            $response->assertSee(route('administracion.usuarios'))->assertSee(route('administracion.auditorias'));
        } else {
            $response->assertDontSee(route('administracion.usuarios'))->assertDontSee(route('administracion.auditorias'));
        }
    }

    public function test_name_update_ignores_privileged_fields_and_another_user_id(): void
    {
        $usuario = User::factory()->create(['activo' => true, 'name' => 'Nombre anterior']);
        $otro = User::factory()->create(['activo' => true]);
        $passwordHash = $usuario->password;
        $this->login($usuario);

        $this->patch(route('perfil.update'), [
            'name' => 'Nombre actualizado', 'id' => $otro->id, 'user_id' => $otro->id,
            'email' => 'otro@example.com', 'activo' => false, 'role' => 'Administrador', 'password' => 'CambioNoPermitido!',
        ])->assertRedirect(route('perfil.show'))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id, 'name' => 'Nombre actualizado', 'email' => $usuario->email,
            'activo' => true, 'password' => $passwordHash,
        ]);
        $this->assertDatabaseHas('users', ['id' => $otro->id, 'name' => $otro->name]);
        $this->assertFalse($usuario->fresh()->hasRole('Administrador'));
        $audit = Auditoria::where('accion', 'actualizacion')->sole();
        $this->assertSame($usuario->id, $audit->usuario_id);
        $this->assertSame(['name' => 'Nombre anterior'], $audit->valores_anteriores);
        $this->assertSame(['name' => 'Nombre actualizado'], $audit->valores_nuevos);
    }

    #[TestWith([null, 'Ingresa tu nombre completo.'])]
    #[TestWith([['nombre'], 'El nombre debe ser un texto.'])]
    public function test_invalid_name_is_rejected(mixed $name, string $message): void
    {
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);

        $this->from(route('perfil.show'))->patch(route('perfil.update'), ['name' => $name])
            ->assertRedirect(route('perfil.show'))->assertSessionHasErrorsIn('perfil', ['name' => $message]);

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'name' => $usuario->name]);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'actualizacion']);
    }

    public function test_name_length_is_limited_and_unchanged_name_does_not_create_audit_noise(): void
    {
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);

        $this->patch(route('perfil.update'), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrorsIn('perfil', ['name' => 'El nombre no debe superar los 255 caracteres.']);
        $this->patch(route('perfil.update'), ['name' => $usuario->name])->assertRedirect(route('perfil.show'));

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'name' => $usuario->name]);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'actualizacion']);
    }

    public function test_password_change_preserves_current_session_deadline_and_audits_without_secrets(): void
    {
        $this->freezeTime();
        Mail::fake();
        $usuario = User::factory()->create(['activo' => true]);
        $otro = User::factory()->create(['activo' => true]);
        $this->login($usuario);
        $expiresAt = session('auth_window.expires_at');
        $sessionId = session()->getId();
        DB::table('password_reset_tokens')->insert([
            'email' => $usuario->email, 'token' => Hash::make('grant'), 'created_at' => now(),
            'attempts' => 0, 'verified' => true,
        ]);
        $this->travel(2)->hours();

        $this->put(route('perfil.password.update'), [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
            'user_id' => $otro->id, 'email' => $otro->email,
        ])->assertRedirect(route('perfil.show'))->assertSessionHasNoErrors()
            ->assertSessionHas('auth_window.expires_at', $expiresAt)
            ->assertCookieExpired('auth_workday')->assertCookieExpired(Auth::guard('web')->getRecallerName());

        $this->assertTrue(Hash::check('NuevaClave123!', $usuario->fresh()->password));
        $this->assertTrue(Hash::check('password', $otro->fresh()->password));
        $this->assertNotSame($usuario->remember_token, $usuario->fresh()->remember_token);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $usuario->email]);
        $audit = Auditoria::where('accion', 'cambio_contrasena')->sole();
        $this->assertSame($usuario->id, $audit->registro_id);
        $this->assertSame($usuario->id, $audit->usuario_id);
        $this->assertNull($audit->valores_anteriores);
        $this->assertSame(['contrasena_modificada' => true], $audit->valores_nuevos);
        Mail::assertSent(PasswordChanged::class, fn (PasswordChanged $mail) => $mail->hasTo($usuario->email) && $mail->fromProfile);

        Auth::forgetGuards();
        $this->get(route('perfil.show'))->assertOk();
        $this->assertAuthenticatedAs($usuario);
        $this->travel(6)->hours();
        Auth::forgetGuards();
        $this->get(route('perfil.show'))->assertRedirect(route('login'));
    }

    public function test_old_access_is_rejected_without_revoking_the_updated_remembered_browser(): void
    {
        $this->freezeTime();
        Mail::fake();
        $usuario = User::factory()->create(['activo' => true]);
        $login = $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password', 'remember' => 'on']);
        $oldSession = session()->all();
        $recaller = Auth::guard('web')->getRecallerName();
        $oldCookies = [$recaller => $login->getCookie($recaller)->getValue(), 'auth_workday' => $login->getCookie('auth_workday')->getValue()];
        $expiresAt = $login->getCookie('auth_workday')->getExpiresTime();
        $this->travel(2)->hours();
        $this->travel(37)->seconds();

        $response = $this->withCookies($oldCookies)->put(route('perfil.password.update'), [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('perfil.show'))->assertSessionHasNoErrors();
        $newCookies = [$recaller => $response->getCookie($recaller)->getValue(), 'auth_workday' => $response->getCookie('auth_workday')->getValue()];
        $this->assertSame($expiresAt, $response->getCookie('auth_workday')->getExpiresTime());

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withCookies($oldCookies)->withSession($oldSession)->get(route('perfil.show'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withCookies($oldCookies)->get(route('perfil.show'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withCookies($newCookies)->get(route('perfil.show'))->assertOk();
        $this->assertAuthenticatedAs($usuario);
        Mail::assertSent(PasswordChanged::class, 1);
    }

    /** @return array<string, array{array<string, mixed>, array<string, string>}> */
    public static function invalidPasswords(): array
    {
        return [
            'missing' => [['current_password' => null, 'password' => null], ['current_password' => 'Ingresa tu contraseña actual.', 'password' => 'Ingresa tu nueva contraseña.']],
            'wrong current' => [['current_password' => 'incorrect'], ['current_password' => 'La contraseña actual es incorrecta.']],
            'current array' => [['current_password' => ['incorrect']], ['current_password' => 'La contraseña actual debe ser un texto.']],
            'new array' => [['password' => ['incorrect']], ['password' => 'La nueva contraseña debe ser un texto.']],
            'short' => [['password' => 'Clave!'], ['password' => 'La contraseña debe contener al menos 8 caracteres.']],
            'long' => [['password' => str_repeat('A', 72).'!'], ['password' => 'La contraseña no debe superar los 72 caracteres.']],
            'confirmation' => [['password_confirmation' => 'OtraClave123!'], ['password' => 'Las contraseñas no coinciden.']],
            'no uppercase' => [['password' => 'nuevaclave!', 'password_confirmation' => 'nuevaclave!'], ['password' => 'La contraseña debe contener al menos una letra mayúscula.']],
            'no symbol' => [['password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'], ['password' => 'La contraseña debe contener al menos un símbolo.']],
            'same password' => [['password' => 'password', 'password_confirmation' => 'password'], ['password' => 'La nueva contraseña debe ser diferente de la actual.']],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_changes_do_not_modify_the_account_or_store_secrets(array $overrides, array $errors): void
    {
        Mail::fake();
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);

        $this->from(route('perfil.show'))->put(route('perfil.password.update'), array_replace([
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ], $overrides))->assertRedirect(route('perfil.show'))->assertSessionHasErrorsIn('password', $errors)
            ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'password' => $usuario->password, 'remember_token' => $usuario->remember_token]);
        $this->assertDatabaseMissing('auditorias', ['accion' => 'cambio_contrasena']);
        Mail::assertNothingSent();
    }

    public function test_password_attempts_are_limited_without_leaving_the_profile_or_storing_passwords(): void
    {
        $this->freezeTime();
        Mail::fake();
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->put(route('perfil.password.update'), ['current_password' => 'incorrect'])->assertSessionHasErrorsIn('password');
        }

        $this->put(route('perfil.password.update'), [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('perfil.show'))->assertHeader('Retry-After', '60')
            ->assertSessionHasErrorsIn('password', ['throttle' => 'Has realizado demasiados intentos. Espera 60 segundos antes de volver a intentarlo.'])
            ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password');

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'password' => $usuario->password]);
        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_undo_the_password_change(): void
    {
        Exceptions::fake();
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);
        Mail::shouldReceive('to')->once()->with($usuario->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(\Mockery::type(PasswordChanged::class))->andThrow(new RuntimeException('SMTP unavailable'));

        $this->put(route('perfil.password.update'), [
            'current_password' => 'password', 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('perfil.show'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('NuevaClave123!', $usuario->fresh()->password));
        $this->assertDatabaseHas('auditorias', ['accion' => 'cambio_contrasena', 'registro_id' => $usuario->id]);
        Exceptions::assertReported(RuntimeException::class);
    }

    #[TestWith(['PATCH', 'perfil.update'])]
    #[TestWith(['PUT', 'perfil.password.update'])]
    public function test_expired_profile_forms_return_to_the_profile_without_retaining_secrets(string $method, string $route): void
    {
        $usuario = User::factory()->create(['activo' => true]);
        $this->login($usuario);
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $this->call($method, route($route), ['_token' => 'expired', 'current_password' => 'secret', 'password' => 'Secret123!'])
            ->assertRedirect(route('perfil.show'))->assertSessionHasErrors('session')
            ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password');

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'password' => $usuario->password]);
    }

    public function test_profile_password_email_describes_the_correct_origin_and_session_behavior(): void
    {
        $mail = new PasswordChanged('29/10/2026 10:30 (America/Tegucigalpa)', fromProfile: true);
        $mail->assertSeeInText('desde Mi perfil');
        $mail->assertSeeInText('La sesión donde realizaste el cambio permanece abierta');
        $mail->assertDontSeeInText('mediante el proceso de recuperación');
    }

    private function login(User $usuario): void
    {
        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }
}
