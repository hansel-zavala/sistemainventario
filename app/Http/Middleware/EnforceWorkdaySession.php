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
                Auth::logout();
                Cookie::queue(Cookie::forget('auth_workday'));
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                $request->session()->put('auth_window', $window);
            }
        }

        return $next($request);
    }
}
