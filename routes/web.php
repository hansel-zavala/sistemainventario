<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use App\Livewire\Administracion\Usuarios\Index as UsuariosIndex;
use App\Livewire\Catalogos\Categorias\Index as CategoriasIndex;
use App\Livewire\Catalogos\TiposEquipo\Index as TiposEquipoIndex;
use App\Livewire\Catalogos\Marcas\Index as MarcasIndex;
use App\Livewire\Catalogos\Departamentos\Index as DepartamentosIndex;
use App\Livewire\Catalogos\UnidadesMedida\Index as UnidadesMedidaIndex;
use App\Livewire\Inventario\Equipos\Index as EquiposIndex;
use App\Livewire\Inventario\Herramientas\Index as HerramientasIndex;

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

    Route::get(
        '/catalogos/categorias',
        CategoriasIndex::class
    )->name('catalogos.categorias');

    Route::get(
        '/catalogos/tipos-equipo',
        TiposEquipoIndex::class
    )->name('catalogos.tipos-equipo');

    Route::get(
        '/catalogos/marcas',
        MarcasIndex::class
    )->name('catalogos.marcas');

    Route::get(
        '/catalogos/departamentos',
        DepartamentosIndex::class
    )->name('catalogos.departamentos');

    Route::get(
        '/catalogos/unidades-medida',
        UnidadesMedidaIndex::class
    )->name('catalogos.unidades-medida');

    Route::get(
        '/inventario/equipos',
        EquiposIndex::class
    )->name('inventario.equipos');

    Route::get(
        '/inventario/herramientas',
        HerramientasIndex::class
    )->name('inventario.herramientas');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});