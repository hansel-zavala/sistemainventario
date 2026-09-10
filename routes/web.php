<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use App\Livewire\Administracion\Usuarios\Index as UsuariosIndex;

Route::middleware('guest')->group(function () {

    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::middleware('role:Administrador')->group(function () {
        Route::get(
            '/administracion/usuarios',
            UsuariosIndex::class
        )->name('administracion.usuarios');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});