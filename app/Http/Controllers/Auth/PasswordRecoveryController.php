<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.recovery', ['step' => 'email']);
    }

    public function send(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $recovery->sendCode($data['email']);
        $request->session()->forget('password_recovery');
        $request->session()->put('password_recovery.email', $data['email']);

        return redirect()->route('password.code')->with('status',
            'Si el correo corresponde a una cuenta activa, recibirás un código. Revisa también la carpeta de spam. Puedes solicitar otro después de 60 segundos.');
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('password_recovery.email')) {
            return redirect()->route('password.request');
        }

        $email = Str::lower($request->session()->get('password_recovery.email'));

        /** Laravel's named throttle middleware hashes the limiter name together with each limit key. */
        $emailKey = md5('recovery-sendrecovery-email:'.$email);
        $ipKey = md5('recovery-sendrecovery-ip:'.$request->ip());
        $resendSeconds = max(
            RateLimiter::availableIn($emailKey),
            RateLimiter::tooManyAttempts($ipKey, 5) ? RateLimiter::availableIn($ipKey) : 0,
        );

        return view('auth.recovery', ['step' => 'code', 'resendSeconds' => $resendSeconds]);
    }

    public function verify(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']], [
            'code.required' => 'Ingresa el código que recibiste por correo.',
            'code.regex' => 'El código debe contener 6 dígitos.',
        ]);
        $email = $request->session()->get('password_recovery.email');
        $grant = is_string($email) ? $recovery->verifyCode($email, $data['code']) : null;

        if ($grant === null) {
            return back()->withErrors(['code' => 'El código es incorrecto, venció o alcanzó el límite de 5 intentos.']);
        }

        $request->session()->regenerate();
        $request->session()->put('password_recovery.grant', $grant);
        $request->session()->put('password_recovery.expires_at', now()->addMinutes(10)->timestamp);

        return redirect()->route('password.reset');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (! $this->hasGrant($request)) {
            return redirect()->route('password.request')->withErrors(['code' => 'Solicita y verifica un código para cambiar tu contraseña.']);
        }

        return view('auth.recovery', ['step' => 'password']);
    }

    public function update(Request $request, PasswordRecovery $recovery): RedirectResponse
    {
        if (! $this->hasGrant($request)) {
            return redirect()->route('password.request')->withErrors(['code' => 'Solicita y verifica un código para cambiar tu contraseña.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.required' => 'Ingresa tu nueva contraseña.',
            'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe superar los 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if (! $recovery->resetPassword(
            $request->session()->get('password_recovery.email'),
            $request->session()->get('password_recovery.grant'),
            $data['password'],
        )) {
            $request->session()->forget('password_recovery');

            return redirect()->route('password.request')->withErrors(['code' => 'La autorización venció o ya fue utilizada. Solicita un nuevo código.']);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Tu contraseña se actualizó. Inicia sesión con tu nueva contraseña.');
    }

    private function hasGrant(Request $request): bool
    {
        return is_string($request->session()->get('password_recovery.email'))
            && is_string($request->session()->get('password_recovery.grant'))
            && $request->session()->get('password_recovery.expires_at', 0) > now()->timestamp;
    }
}
