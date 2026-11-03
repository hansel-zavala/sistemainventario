<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChanged;
use App\Models\User;
use App\Services\RegistrarAuditoria;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PerfilController extends Controller
{
    public function show(Request $request): View
    {
        return view('perfil', ['usuario' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('perfil', [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Ingresa tu nombre completo.',
            'name.string' => 'El nombre debe ser un texto.',
            'name.max' => 'El nombre no debe superar los 255 caracteres.',
        ]);

        DB::transaction(function () use ($request, $data): void {
            $usuario = $request->user();
            $usuario->name = $data['name'];
            RegistrarAuditoria::guardarActualizacion($usuario);
        });

        return redirect()->route('perfil.show')->with('status', 'Tu información se actualizó correctamente.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['bail', 'required', 'string', 'current_password:web'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'max:72', 'confirmed', 'different:current_password',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! preg_match('/\p{Lu}/u', $value)) {
                        $fail('La contraseña debe contener al menos una letra mayúscula.');
                    }
                },
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! preg_match('/[\p{P}\p{S}]/u', $value)) {
                        $fail('La contraseña debe contener al menos un símbolo.');
                    }
                },
            ],
        ], [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.string' => 'La contraseña actual debe ser un texto.',
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.required' => 'Ingresa tu nueva contraseña.',
            'password.string' => 'La nueva contraseña debe ser un texto.',
            'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe superar los 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.different' => 'La nueva contraseña debe ser diferente de la actual.',
        ]);

        $usuario = DB::transaction(function () use ($request, $data): User {
            $usuario = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if (! $usuario->activo || ! Hash::check($data['current_password'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'La contraseña actual es incorrecta o la cuenta ya no está disponible.',
                ])->errorBag('password');
            }

            $usuario->password = $data['password'];
            $usuario->setRememberToken(Str::random(60));
            $usuario->save();
            DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
            RegistrarAuditoria::cambioContrasena($usuario);

            return $usuario;
        });

        $window = $request->session()->get('auth_window');
        $window['password'] = hash('sha256', $usuario->getAuthPassword());
        $remember = $request->hasCookie('auth_workday');
        $remainingMinutes = (int) ceil(max(0, $window['expires_at'] - now()->timestamp) / 60);

        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $guard->setRememberDuration($remainingMinutes);
        $guard->login($usuario, $remember);
        $request->session()->regenerateToken();
        $request->session()->put('auth_window', $window);
        $request->session()->forget('password_recovery');

        if ($remember) {
            Cookie::queue(Cookie::make('auth_workday', json_encode($window), $remainingMinutes)
                ->withExpires($window['expires_at']));
        } else {
            Cookie::queue(Cookie::forget('auth_workday'));
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
        }

        try {
            Mail::to($usuario->email)->send(new PasswordChanged(
                now()->format('d/m/Y H:i').' ('.config('app.timezone').')',
                fromProfile: true,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('perfil.show')->with('status',
            'Tu contraseña se actualizó. Los demás accesos quedaron invalidados; puedes continuar en esta sesión.');
    }
}
