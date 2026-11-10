<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\EquiposExportController;
use App\Http\Controllers\InventoryExportController;
use App\Http\Controllers\PerfilController;
use App\Livewire\Administracion\Auditorias\Index as AuditoriasIndex;
use App\Livewire\Administracion\Usuarios\Index as UsuariosIndex;
use App\Livewire\Catalogos\Categorias\Index as CategoriasIndex;
use App\Livewire\Catalogos\Departamentos\Index as DepartamentosIndex;
use App\Livewire\Catalogos\Marcas\Index as MarcasIndex;
use App\Livewire\Catalogos\TiposEquipo\Index as TiposEquipoIndex;
use App\Livewire\Catalogos\UnidadesMedida\Index as UnidadesMedidaIndex;
use App\Livewire\Historial\Movimientos\Index as HistorialMovimientosIndex;
use App\Livewire\Inventario\Equipos\Index as EquiposIndex;
use App\Livewire\Inventario\Herramientas\Index as HerramientasIndex;
use App\Livewire\Inventario\Insumos\Index as InsumosIndex;
use App\Livewire\Inventario\Tintas\Index as TintasIndex;
use App\Livewire\Inventario\Tintas\Recargas\Index as RecargasTintaIndex;
use App\Livewire\Movimientos\Devoluciones\Index as DevolucionesIndex;
use App\Livewire\Movimientos\Entradas\Index as EntradasIndex;
use App\Livewire\Movimientos\Prestamos\Index as PrestamosIndex;
use App\Livewire\Movimientos\Salidas\Index as SalidasIndex;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {

    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/olvide-mi-contrasena', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/olvide-mi-contrasena', [PasswordRecoveryController::class, 'send'])
        ->middleware('throttle:recovery-send')->name('password.email');
    Route::get('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'code'])->name('password.code');
    Route::post('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'verify'])
        ->middleware('throttle:recovery-verify')->name('password.verify');
    Route::get('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'edit'])->name('password.reset');
    Route::post('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'update'])
        ->middleware('throttle:recovery-verify')->name('password.update');
});

Route::middleware('auth')->group(function () {

    Route::get('/inventario/equipos/exportar', EquiposExportController::class)
        ->name('inventario.equipos.exportar');

    Route::get('/inventario/herramientas/exportar', InventoryExportController::class)
        ->defaults('module', 'herramientas')->name('inventario.herramientas.exportar');

    Route::get('/inventario/insumos/exportar', InventoryExportController::class)
        ->defaults('module', 'insumos')->name('inventario.insumos.exportar');

    Route::get('/inventario/tintas/exportar', InventoryExportController::class)
        ->defaults('module', 'tintas')->name('inventario.tintas.exportar');

    Route::get('/catalogos/categorias/exportar', InventoryExportController::class)
        ->defaults('module', 'categorias')->name('catalogos.categorias.exportar');

    Route::get('/catalogos/tipos-equipo/exportar', InventoryExportController::class)
        ->defaults('module', 'tipos-equipo')->name('catalogos.tipos-equipo.exportar');

    Route::get('/catalogos/marcas/exportar', InventoryExportController::class)
        ->defaults('module', 'marcas')->name('catalogos.marcas.exportar');

    Route::get('/catalogos/departamentos/exportar', InventoryExportController::class)
        ->defaults('module', 'departamentos')->name('catalogos.departamentos.exportar');

    Route::get('/catalogos/unidades-medida/exportar', InventoryExportController::class)
        ->defaults('module', 'unidades-medida')->name('catalogos.unidades-medida.exportar');

    Route::get('/mi-perfil', [PerfilController::class, 'show'])->name('perfil.show');
    Route::patch('/mi-perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/mi-perfil/contrasena', [PerfilController::class, 'updatePassword'])
        ->middleware('throttle:profile-password')->name('perfil.password.update');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::middleware('role:Administrador')->group(function () {

        Route::get('/administracion/usuarios/exportar', InventoryExportController::class)
            ->defaults('module', 'usuarios')->name('administracion.usuarios.exportar');

        Route::get(
            '/administracion/usuarios',
            UsuariosIndex::class
        )->name('administracion.usuarios');

        Route::get(
            '/administracion/auditorias',
            AuditoriasIndex::class
        )->name('administracion.auditorias');
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

    Route::get(
        '/inventario/insumos',
        InsumosIndex::class
    )->name('inventario.insumos');

    Route::get(
        '/inventario/tintas',
        TintasIndex::class
    )->name('inventario.tintas');

    Route::get(
        '/inventario/tintas/recargas',
        RecargasTintaIndex::class
    )->name('inventario.tintas.recargas');

    Route::get(
        '/movimientos/entradas',
        EntradasIndex::class
    )->name('movimientos.entradas');

    Route::get(
        '/movimientos/salidas',
        SalidasIndex::class
    )->name('movimientos.salidas');

    Route::get(
        '/movimientos/prestamos',
        PrestamosIndex::class
    )->name('movimientos.prestamos');

    Route::get(
        '/movimientos/devoluciones',
        DevolucionesIndex::class
    )->name('movimientos.devoluciones');

    Route::get(
        '/historial/movimientos',
        HistorialMovimientosIndex::class
    )->name('historial.movimientos');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
