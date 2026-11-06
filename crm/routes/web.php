<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\ImportacionController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PlantillaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.entrar');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', PanelController::class)->name('panel');

    // Empresas y su historial
    Route::get('/empresas/exportar', [EmpresaController::class, 'exportar'])->name('empresas.exportar');
    Route::resource('empresas', EmpresaController::class);
    Route::patch('/empresas/{empresa}/estado', [EmpresaController::class, 'estado'])->name('empresas.estado');
    Route::patch('/empresas/{empresa}/proxima-accion', [EmpresaController::class, 'proximaAccion'])->name('empresas.proxima');
    Route::post('/empresas/{empresa}/contactos', [ContactoController::class, 'store'])->name('contactos.store');
    Route::patch('/contactos/{contacto}', [ContactoController::class, 'update'])->name('contactos.update');
    Route::delete('/contactos/{contacto}', [ContactoController::class, 'destroy'])->name('contactos.destroy');

    // Importación de Excels
    Route::get('/importar', [ImportacionController::class, 'index'])->name('importar');
    Route::post('/importar/revisar', [ImportacionController::class, 'revisar'])->name('importar.revisar');
    Route::post('/importar/confirmar', [ImportacionController::class, 'confirmar'])->name('importar.confirmar');

    // Clientes y pagos
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/empresas/{empresa}/alta-cliente', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/empresas/{empresa}/alta-cliente', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::post('/clientes/{cliente}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::delete('/pagos/{pago}', [PagoController::class, 'destroy'])->name('pagos.destroy');

    // Plantillas y estadísticas
    Route::resource('plantillas', PlantillaController::class)->except('show');
    Route::get('/estadisticas', EstadisticaController::class)->name('estadisticas');

    // Ajustes
    Route::get('/ajustes', [AjusteController::class, 'index'])->name('ajustes');
    Route::put('/ajustes', [AjusteController::class, 'guardar'])->name('ajustes.guardar');
    Route::post('/ajustes/rubros', [AjusteController::class, 'rubroStore'])->name('rubros.store');
    Route::patch('/ajustes/rubros/{rubro}', [AjusteController::class, 'rubroUpdate'])->name('rubros.update');
    Route::put('/ajustes/password', [AjusteController::class, 'password'])->name('ajustes.password');
});
