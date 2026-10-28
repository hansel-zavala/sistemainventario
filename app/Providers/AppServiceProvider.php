<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login:'.$this->emailKey($request).'|'.$request->ip()),
        ]);
        RateLimiter::for('recovery-send', fn (Request $request) => [
            Limit::perMinute(5)->by('recovery-ip:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->recoveryCooldownResponse($request, $headers)),
            Limit::perMinute(1)->by('recovery-email:'.$this->emailKey($request))
                ->response(fn (Request $request, array $headers) => $this->recoveryCooldownResponse($request, $headers)),
        ]);
        RateLimiter::for('recovery-verify', fn (Request $request) => Limit::perMinute(10)->by('recovery-verify:'.$request->ip()));
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
}
