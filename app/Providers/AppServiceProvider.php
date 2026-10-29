<?php

namespace App\Providers;

use App\Models\User;
use App\Services\RegistrarAuditoria;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->authenticationCooldownResponse($request, $headers)),
            Limit::perMinute(5)->by('login:'.$this->emailKey($request).'|'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->authenticationCooldownResponse($request, $headers)),
        ]);
        RateLimiter::for('recovery-send', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery-ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->recoveryCooldownResponse($request, $headers)),
            Limit::perMinute(1)->by('recovery-email:'.$this->emailKey($request))
                ->response(fn (Request $request, array $headers) => $this->recoveryCooldownResponse($request, $headers)),
        ]);
        RateLimiter::for('recovery-verify', fn (Request $request) => Limit::perMinute(10)->by('recovery-verify:'.$request->ip())
            ->response(fn (Request $request, array $headers) => $this->authenticationCooldownResponse($request, $headers)));
    }

    private function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return Str::lower(is_string($email) ? $email : '');
    }

    /** @param array<string, int|string> $headers */
    private function recoveryCooldownResponse(Request $request, array $headers): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($request->session()->get('password_recovery.email') !== $data['email']) {
            $request->session()->forget('password_recovery');
        }

        $request->session()->put('password_recovery.email', $data['email']);

        return redirect()->route('password.code')->withHeaders($headers)->with('status',
            'Espera antes de solicitar otro código. Si ya recibiste uno, puedes ingresarlo o esperar a que termine la cuenta regresiva.');
    }

    /** @param array<string, int|string> $headers */
    private function authenticationCooldownResponse(Request $request, array $headers): RedirectResponse|JsonResponse
    {
        $seconds = (int) $headers['Retry-After'];
        $message = "Has realizado demasiados intentos. Espera {$seconds} segundos antes de volver a intentarlo.";

        if ($request->routeIs('login.store')) {
            $auditKey = 'audit:login-blocked:'.hash('sha256', (string) $request->ip());

            if (Cache::add($auditKey, true, now()->addMinute())) {
                $usuario = User::query()->where('email', $this->emailKey($request))->first();
                RegistrarAuditoria::eventoAutenticacion(
                    'bloqueo_login',
                    $usuario,
                    $request,
                    ['resultado' => 'demasiados_intentos'],
                );
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, $headers);
        }

        if ($request->routeIs('login.store')) {
            return redirect()->route('login')->withHeaders($headers)->withErrors(['throttle' => $message])->withInput([
                'email' => is_string($request->input('email')) ? $request->input('email') : '',
                'remember' => $request->boolean('remember'),
            ]);
        }

        $route = $request->routeIs('password.update') ? 'password.reset' : 'password.code';

        if (! $request->session()->has('password_recovery.email')) {
            $route = 'password.request';
        }

        return redirect()->route($route)->withHeaders($headers)->withErrors(['throttle' => $message]);
    }
}
