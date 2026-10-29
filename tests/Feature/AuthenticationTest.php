<?php

namespace Tests\Feature;

use App\Mail\PasswordChanged;
use App\Mail\PasswordRecoveryCode;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_09_095145_create_permission_tables.php',
            'database/migrations/2026_09_09_095349_add_activo_to_users_table.php',
            'database/migrations/2026_10_27_120000_add_verification_fields_to_password_reset_tokens_table.php',
        ]];
    }

    public function test_remember_restores_access_without_the_original_session_and_expires_after_eight_hours(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $response = $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password', 'remember' => 'on',
        ])->assertRedirect(route('dashboard'));

        $recallerName = Auth::guard('web')->getRecallerName();
        $recaller = $response->getCookie($recallerName);
        $window = $response->getCookie('auth_workday');
        $this->assertSame(now()->addHours(8)->timestamp, $recaller->getExpiresTime());
        $this->assertSame(now()->addHours(8)->timestamp, $window->getExpiresTime());

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->travel(7)->hours();
        $this->withCookies([$recallerName => $recaller->getValue(), 'auth_workday' => $window->getValue()])
            ->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);

        Auth::forgetGuards();
        $this->travel(1)->hours();
        $this->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertSessionHas('auth_notice', 'Tu jornada de 8 horas terminó. Inicia sesión nuevamente.');
        $this->assertGuest();
        $this->get(route('login'))->assertOk()->assertSee('Tu jornada de 8 horas terminó. Inicia sesión nuevamente.');
        $this->get(route('login'))->assertDontSee('Tu jornada de 8 horas terminó. Inicia sesión nuevamente.');
    }

    public function test_a_session_without_remember_also_cannot_exceed_the_workday(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $response->assertCookieExpired('auth_workday');
        $response->assertCookieExpired(Auth::guard('web')->getRecallerName());
        $this->travel(8)->hours();
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_revokes_the_remembered_login(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $response = $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password', 'remember' => 'on',
        ]);
        $name = Auth::guard('web')->getRecallerName();
        $cookies = [$name => $response->getCookie($name)->getValue(), 'auth_workday' => $response->getCookie('auth_workday')->getValue()];
        $this->post(route('logout'))->assertRedirect(route('login'))->assertCookieExpired('auth_workday');
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withCookies($cookies)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_deactivated_user_loses_existing_access(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $user->update(['activo' => false]);
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertSessionHas('auth_notice', 'Tu cuenta está inactiva. Contacta al Departamento de Informática.');
        $this->assertGuest();
    }

    public function test_wrong_credentials_preserve_remember_and_login_is_rate_limited(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect', 'remember' => 'on'])
                ->assertSessionHasErrors('email')->assertSessionHas('_old_input.remember', 'on');
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertRedirect(route('login'))->assertHeader('Retry-After', '60')
            ->assertSessionHasErrors(['throttle' => 'Has realizado demasiados intentos. Espera 60 segundos antes de volver a intentarlo.'])
            ->assertSessionHas('_old_input.email', $user->email)
            ->assertSessionHas('_old_input.remember', true)
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('login'))->assertSee('Has realizado demasiados intentos.');
        $this->travel(20)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))->assertHeader('Retry-After', '40');
        $this->travel(41)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_login(): void
    {
        $user = User::factory()->create(['activo' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertSessionHasErrors(['email' => 'Este usuario se encuentra inactivo.']);
        $this->assertGuest();
    }

    public function test_recovery_sends_a_hashed_code_and_resets_password_only_after_verification(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $oldToken = $user->remember_token;
        $code = $this->requestCode($user);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($code, $stored);
        $this->assertTrue(Hash::check($code, $stored));

        $this->post(route('password.verify'), ['code' => $code])->assertRedirect(route('password.reset'));
        $grantSession = session('password_recovery');
        $this->get(route('password.reset'))->assertOk()->assertSee('Crea tu nueva contraseña');
        $this->post(route('password.update'), [
            'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        Mail::assertSent(PasswordChanged::class, fn (PasswordChanged $mail) => $mail->hasTo($user->email));
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();

        $this->withSession(['password_recovery' => $grantSession])->post(route('password.update'), [
            'password' => 'OtraClave123!', 'password_confirmation' => 'OtraClave123!',
        ])->assertRedirect(route('password.request'))->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        Mail::assertSent(PasswordChanged::class, 1);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_unknown_and_inactive_accounts_receive_the_same_generic_response(bool $inactive): void
    {
        Mail::fake();
        $email = $inactive ? User::factory()->create(['activo' => false])->email : 'unknown@example.com';
        $this->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.code'))->assertSessionHas('status');
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
    }

    public function test_five_wrong_codes_block_even_the_correct_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post(route('password.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('password.reset'))->assertRedirect(route('password.request'));
    }

    public function test_code_expires_at_ten_minutes(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->travel(10)->minutes();
        $this->post(route('password.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_resending_is_throttled_and_invalidates_the_previous_code(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $oldCode = $this->requestCode($user);
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.code'));
        $this->travel(61)->seconds();
        $newCode = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $oldCode])->assertSessionHasErrors('code');
        $this->post(route('password.verify'), ['code' => $newCode])->assertRedirect(route('password.reset'));
    }

    public function test_password_cannot_be_changed_without_a_verified_session(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $this->requestCode($user);
        $this->post(route('password.update'), [
            'email' => $user->email, 'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Mail::assertNotSent(PasswordChanged::class);
    }

    public function test_verified_permission_expires_and_cannot_be_used_in_another_session(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code])->assertRedirect(route('password.reset'));
        $verifiedSession = session('password_recovery');
        $this->app['session']->flush();
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertRedirect(route('password.request'));
        $this->travel(10)->minutes();
        $this->withSession(['password_recovery' => $verifiedSession])->post(route('password.update'), [
            'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[TestWith(['short', 'short', 'La contraseña debe contener al menos 8 caracteres.'])]
    #[TestWith(['NuevaClave123!', 'OtraClave123!', 'Las contraseñas no coinciden.'])]
    #[TestWith(['nuevaclave123!', 'nuevaclave123!', 'La contraseña debe contener al menos una letra mayúscula.'])]
    #[TestWith(['NuevaClave123', 'NuevaClave123', 'La contraseña debe contener al menos un símbolo.'])]
    #[TestWith(['Nueva Clave123', 'Nueva Clave123', 'La contraseña debe contener al menos un símbolo.'])]
    #[TestWith(['12345678!', '12345678!', 'La contraseña debe contener al menos una letra mayúscula.'])]
    public function test_new_password_must_be_valid_and_confirmed(string $password, string $confirmation, string $error): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code]);
        $this->post(route('password.update'), ['password' => $password, 'password_confirmation' => $confirmation])
            ->assertSessionHasErrors(['password' => $error]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        Mail::assertNotSent(PasswordChanged::class);
    }

    public function test_reset_revokes_an_existing_session_and_remember_cookie(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $login = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $oldSession = session()->all();
        $name = Auth::guard('web')->getRecallerName();
        $cookies = [$name => $login->getCookie($name)->getValue(), 'auth_workday' => $login->getCookie('auth_workday')->getValue()];
        $this->app['session']->flush();
        Auth::forgetGuards();
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code]);
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!']);
        Auth::forgetGuards();
        $this->withSession($oldSession)->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertSessionHas('auth_notice', 'Tu contraseña cambió. Inicia sesión nuevamente con tu nueva contraseña.');
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withCookies($cookies)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_recovery_pages_and_mail_render_in_spanish(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('¿Olvidaste la contraseña?');
        $this->get(route('password.request'))->assertOk()->assertSee('Recuperar contraseña');
        $this->withSession(['password_recovery.email' => 'persona@example.com'])->get(route('password.code'))->assertOk()->assertSee('Código de verificación');
        $mail = new PasswordRecoveryCode('012345');
        $mail->assertSeeInHtml('012345');
        $mail->assertSeeInText('10 minutos');
    }

    #[TestWith(['missing'])]
    #[TestWith(['tampered'])]
    #[TestWith(['expired'])]
    public function test_remember_cannot_restore_access_without_a_valid_workday_cookie(string $condition): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $login = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $name = Auth::guard('web')->getRecallerName();
        $cookies = [$name => $login->getCookie($name, false)->getValue()];
        if ($condition !== 'missing') {
            $cookies['auth_workday'] = $condition === 'tampered' ? 'forged-cookie' : $login->getCookie('auth_workday', false)->getValue();
        }
        if ($condition === 'expired') {
            $this->travel(8)->hours();
        }
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withUnencryptedCookies($cookies)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_new_code_revokes_a_previously_verified_permission(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code]);
        $oldGrant = session('password_recovery');
        $this->travel(61)->seconds();
        $this->requestCode($user);
        $this->withSession(['password_recovery' => $oldGrant])->post(route('password.update'), [
            'password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!',
        ])->assertRedirect(route('password.request'))->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_deactivation_after_code_verification_prevents_reset(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code]);
        $user->update(['activo' => false]);
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertRedirect(route('password.request'))->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_mail_failure_is_reported_and_does_not_leave_a_usable_code(): void
    {
        Exceptions::fake();
        $user = User::factory()->create(['activo' => true]);
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'))->assertSessionHas('status');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_invalid_email_input_is_rejected_by_authentication_endpoints(): void
    {
        Mail::fake();
        $this->post(route('password.email'), ['email' => ['invalid']])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => ['invalid'], 'password' => 'password'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    private function requestCode(User $user): string
    {
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.code'));
        Mail::assertSent(PasswordRecoveryCode::class, fn (PasswordRecoveryCode $mail) => $mail->hasTo($user->email));

        return Mail::sent(PasswordRecoveryCode::class)->last()->code;
    }

    public function test_resend_countdown_uses_server_time_across_reloads_and_restarts_after_resending(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $this->requestCode($user);
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 60)
            ->assertSee('Reenviar código en 60 s');

        $this->travel(20)->seconds();
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 40);
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 40);
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.code'));

        $this->travel(41)->seconds();
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 0);
        $this->requestCode($user);
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 60);
        Mail::assertSentCount(2);
    }

    public function test_failed_verification_does_not_reset_resend_countdown(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->travel(25)->seconds();
        $this->from(route('password.code'))->post(route('password.verify'), [
            'code' => $code === '000000' ? '111111' : '000000',
        ])->assertRedirect(route('password.code'))->assertSessionHasErrors('code');
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 35);
    }

    #[TestWith(['login'])]
    #[TestWith(['password.request'])]
    public function test_restarting_recovery_during_cooldown_returns_to_the_existing_code(string $returnRoute): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->travel(20)->seconds();
        $this->get(route($returnRoute))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'))
            ->assertHeader('Retry-After', '40')
            ->assertSessionHas('password_recovery.email', $user->email)
            ->assertSessionHas('status');
        $this->get(route('password.code'))->assertOk()->assertViewHas('resendSeconds', 40);
        $this->assertEquals($record, DB::table('password_reset_tokens')->where('email', $user->email)->first());
        Mail::assertSentCount(1);
        $this->post(route('password.verify'), ['code' => $code])->assertRedirect(route('password.reset'));
    }

    public function test_cooldown_restores_the_email_after_the_browser_session_is_lost(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $this->requestCode($user);
        $this->app['session']->flush();
        $this->travel(15)->seconds();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.code'))
            ->assertSessionHas('password_recovery.email', $user->email);
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 45);
        Mail::assertSentCount(1);
    }

    public function test_another_email_can_request_a_code_during_the_first_email_cooldown(): void
    {
        Mail::fake();
        $first = User::factory()->create(['activo' => true]);
        $second = User::factory()->create(['activo' => true]);
        $this->requestCode($first);
        $this->requestCode($second);
        $this->assertSame($second->email, session('password_recovery.email'));
        Mail::assertSentCount(2);
    }

    public function test_ip_cooldown_also_redirects_without_sending_or_reusing_another_email_grant(): void
    {
        $this->freezeTime();
        Mail::fake();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => "unknown{$attempt}@example.com"])
                ->assertRedirect(route('password.code'));
        }
        $this->withSession(['password_recovery.grant' => 'previous-email-grant']);
        $this->travel(10)->seconds();
        $this->post(route('password.email'), ['email' => 'another@example.com'])
            ->assertRedirect(route('password.code'))
            ->assertSessionMissing('password_recovery.grant')
            ->assertSessionHas('password_recovery.email', 'another@example.com');
        $this->get(route('password.code'))->assertViewHas('resendSeconds', 50);
        Mail::assertNothingSent();
    }

    public function test_password_changed_mail_explains_the_change_and_how_to_report_it(): void
    {
        $mail = new PasswordChanged('28/10/2026 14:30 (America/Tegucigalpa)');
        $mail->assertSeeInHtml('Tu contraseña fue actualizada');
        $mail->assertSeeInText('28/10/2026 14:30 (America/Tegucigalpa)');
        $mail->assertSeeInText('contacta de inmediato al Departamento de Informática');
    }

    public function test_notification_failure_does_not_undo_password_reset(): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $oldToken = $user->remember_token;
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code])->assertRedirect(route('password.reset'));

        Exceptions::fake();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(\Mockery::type(PasswordChanged::class))
            ->andThrow(new RuntimeException('SMTP unavailable'));
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_verification_rate_limit_keeps_the_form_and_does_not_retain_the_code(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->from(route('password.code'))->post(route('password.verify'), ['code' => $wrong]);
        }
        $this->travel(10)->seconds();
        $this->post(route('password.verify'), ['code' => $code])
            ->assertRedirect(route('password.code'))->assertHeader('Retry-After', '50')
            ->assertSessionHasErrors(['throttle' => 'Has realizado demasiados intentos. Espera 50 segundos antes de volver a intentarlo.'])
            ->assertSessionMissing('_old_input.code');
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('password.code'))->assertOk()->assertSee('Espera 50 segundos');
        Mail::assertSentCount(1);
    }

    public function test_password_update_rate_limit_preserves_the_permission_without_retaining_passwords(): void
    {
        $this->freezeTime();
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code]);
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->from(route('password.reset'))->post(route('password.update'), ['password' => 'short']);
        }
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertRedirect(route('password.reset'))->assertSessionHasErrors('throttle')
            ->assertSessionHas('password_recovery.grant')->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        Mail::assertNotSent(PasswordChanged::class);
        $this->travel(61)->seconds();
        $this->post(route('password.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])
            ->assertRedirect(route('login'));
        Mail::assertSent(PasswordChanged::class, 1);
    }

    #[TestWith(['login.store', 'login'])]
    #[TestWith(['password.email', 'password.request'])]
    #[TestWith(['password.verify', 'password.request'])]
    #[TestWith(['password.update', 'password.request'])]
    public function test_expired_form_redirects_to_a_fresh_form_with_a_message(string $action, string $destination): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->post(route($action), ['_token' => 'expired', 'password' => 'NeverStoreThis', 'code' => '123456'])
            ->assertRedirect(route($destination))
            ->assertSessionHasErrors(['session' => 'La sesión del formulario venció. Vuelve a intentarlo desde esta página.'])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.code');
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route($destination))->assertOk()->assertSee('La sesión del formulario venció.');
    }

    public function test_session_expiration_on_a_post_request_has_the_workday_message(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['activo' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->travel(8)->hours();
        Auth::forgetGuards();
        $this->post(route('logout'))->assertRedirect(route('login'))
            ->assertSessionHas('auth_notice', 'Tu jornada de 8 horas terminó. Inicia sesión nuevamente.');
        $this->assertGuest();
    }

    public function test_login_ip_limit_shows_a_message_even_when_emails_change(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post(route('login.store'), ['email' => "unknown{$attempt}@example.com", 'password' => 'incorrect']);
        }
        $this->post(route('login.store'), ['email' => 'another@example.com', 'password' => 'incorrect'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('throttle');
        $this->postJson(route('login.store'), ['email' => 'another@example.com', 'password' => 'incorrect'])
            ->assertTooManyRequests()->assertJsonPath('message', 'Has realizado demasiados intentos. Espera 60 segundos antes de volver a intentarlo.');
    }

    #[TestWith(['Ábcdefg!'])]
    #[TestWith(['ABCDEFG!'])]
    #[TestWith(['ClaveLarga€'])]
    public function test_password_accepts_uppercase_and_symbols_without_requiring_lowercase_or_digits(string $password): void
    {
        Mail::fake();
        $user = User::factory()->create(['activo' => true]);
        $code = $this->requestCode($user);
        $this->post(route('password.verify'), ['code' => $code])->assertRedirect(route('password.reset'));
        $this->post(route('password.update'), ['password' => $password, 'password_confirmation' => $password])
            ->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        Mail::assertSent(PasswordChanged::class, 1);
    }
}
