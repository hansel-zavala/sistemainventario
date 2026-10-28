<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnforceWorkdaySession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $window = $request->session()->get('auth_window')
                ?? json_decode((string) $request->cookie('auth_workday', ''), true);

            if (! is_array($window)
                || ($window['user_id'] ?? null) !== $user->id
                || ($window['expires_at'] ?? 0) <= now()->timestamp
                || ! hash_equals(hash('sha256', $user->getAuthPassword()), $window['password'] ?? '')
                || ! $user->activo) {
                $message = match (true) {
                    ! $user->activo => 'Tu cuenta está inactiva. Contacta al Departamento de Informática.',
                    is_array($window) && isset($window['password']) && ! hash_equals(hash('sha256', $user->getAuthPassword()), $window['password']) => 'Tu contraseña cambió. Inicia sesión nuevamente con tu nueva contraseña.',
                    is_array($window) && isset($window['expires_at']) && $window['expires_at'] <= now()->timestamp => 'Tu sesión vencio. Inicia sesión nuevamente.',
                    default => 'Tu sesión venció o ya no está disponible. Inicia sesión nuevamente.',
                };
                Auth::logout();
                Cookie::queue(Cookie::forget('auth_workday'));
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->flash('auth_notice', $message);

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 401);
                }

                if (! $request->routeIs('login')) {
                    return redirect()->route('login');
                }
            } else {
                $request->session()->put('auth_window', $window);
            }
        }

        return $next($request);
    }
}
