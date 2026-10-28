<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        Auth::guard('web')->setRememberDuration(480);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'Las credenciales ingresadas no son correctas.',
                ])
                ->onlyInput('email', 'remember');
        }

        $request->session()->regenerate();

        if (! Auth::user()->activo) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => 'Este usuario se encuentra inactivo.',
                ]);
        }

        $window = [
            'user_id' => Auth::id(),
            'expires_at' => now()->addHours(8)->timestamp,
            'password' => hash('sha256', Auth::user()->getAuthPassword()),
        ];
        $request->session()->put('auth_window', $window);

        if ($request->boolean('remember')) {
            Cookie::queue(Cookie::make('auth_workday', json_encode($window), 480));
        } else {
            Cookie::queue(Cookie::forget('auth_workday'));
            Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        Cookie::queue(Cookie::forget('auth_workday'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
