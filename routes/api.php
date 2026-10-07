<?php

use App\Http\Controllers\CamaraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\FrutasController;
use App\Http\Controllers\ProvedorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CajaChicaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [UserController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active-session'])->group(function () {
    Route::get('/user', [\App\Http\Controllers\SessionController::class, 'show']);
    Route::post('auth/listar-usuarios',[UserController::class,'index'])->middleware('access:admin');
    Route::post('auth/make/user',[UserController::class,'store'])->middleware('access:admin');
    Route::post('auth/update/user',[UserController::class,'editarUsuario'])->middleware('access:admin');
    Route::post('auth/cambiar-estado/user',[UserController::class,'cambiarEstadoUsuario'])->middleware('access:admin');
    Route::post('auth/cambiar-contra/user',[UserController::class,'cambiarContraUsuario'])->middleware('access:admin');
    Route::post('auth/roler-user/asignar',[UserController::class,'asignacionRol'])->middleware('access:admin');
    Route::get('provedor/index',[ProvedorController::class,'index'])->middleware('access:read-ajustes,read-compra,create-compra,update-compra');
    Route::post('provedor/update',[ProvedorController::class,'update'])->middleware('access:update-ajustes');
    Route::post('provedor/store',[ProvedorController::class,'store'])->middleware('access:create-ajustes');
    Route::post('provedor/cambiar-estado',[ProvedorController::class,'changer'])->middleware('access:delete-ajustes');
    Route::post('compra/store',[CompraController::class,'store'])->middleware('access:create-compra');
    Route::post('compra/update',[CompraController::class,'update'])->middleware('access:update-compra');
    Route::post('compra/editar-estado',[CompraController::class,'editarEstado'])->middleware('access:delete-compra');
    Route::post('compra/index',[CompraController::class,'listarCompra'])->middleware('access:read-compra');
    Route::post('compra/show',[CompraController::class,'show'])->middleware('access:read-compra');
    Route::get('compra/{id}/edicion', [\App\Http\Controllers\OrderEditorController::class, 'show'])->middleware('access:update-compra');
    Route::post('compra/detalle', [CompraController::class, 'detalle'])->middleware('access:read-compra');
    Route::post('compra/one-frutas',[CompraController::class,'onePeido'])->middleware('access:read-compra');
    Route::get('camara/index',[CamaraController::class,'index'])->middleware('access:read-camara');
    Route::get('camara/cantidades',[CamaraController::class,'listarCantidades'])->middleware('access:read-camara');
    Route::post('camara/one-lote',[CamaraController::class,'listarOneLote'])->middleware('access:read-camara');
    Route::post('fruta/listar',[FrutasController::class,'index'])->middleware('access:read-compra,create-compra,update-compra,create-venta');
    Route::get('cliente/listar',[ClienteController::class,'index'])->middleware('access:read-ajustes,read-venta,create-venta');
    Route::post('cliente/store',[ClienteController::class,'store'])->middleware('access:create-ajustes');
    Route::post('cliente/update',[ClienteController::class,'update'])->middleware('access:update-ajustes');
    Route::post('cliente/cambiar-estado',[ClienteController::class,'changer'])->middleware('access:delete-ajustes');
    Route::post('envio/store',[EnvioController::class,'store'])->middleware('access:create-venta');
    Route::post('cajachica/store',[CajachicaController::class,'store'])->middleware('access:create-caja');
    Route::post('cajachica/show',[CajachicaController::class,'show'])->middleware('access:read-caja');
    Route::get('cajachica/index',[CajachicaController::class,'index'])->middleware('access:read-caja');
    Route::post('auth/logout', [UserController::class, 'logout']);
});
