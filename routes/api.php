<?php
    
use App\Http\Controllers\CamaraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\FrutasController;
use App\Http\Controllers\ProvedorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CajaChicaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\OperationalController;
use App\Http\Controllers\PerdidaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [UserController::class, 'login'])->middleware('throttle:login');



Route::middleware(['auth:sanctum', 'active-session'])->group(function () {
    Route::get('/user', [\App\Http\Controllers\SessionController::class, 'show']);
    Route::get('auth/roles', [UserController::class, 'roles'])->middleware('access:admin');
    Route::post('auth/listar-usuarios',[UserController::class,'index'])->middleware('access:admin');
    Route::post('auth/make/user',[UserController::class,'store'])->middleware(['access:admin', 'audit-admin']);
    Route::post('auth/update/user',[UserController::class,'editarUsuario'])->middleware(['access:admin', 'audit-admin']);
    Route::post('auth/cambiar-estado/user',[UserController::class,'cambiarEstadoUsuario'])->middleware(['access:admin', 'audit-admin']);
    Route::post('auth/cambiar-contra/user',[UserController::class,'cambiarContraUsuario'])->middleware(['access:admin', 'audit-admin']);
    Route::post('auth/roler-user/asignar',[UserController::class,'asignacionRol'])->middleware(['access:admin', 'audit-admin']);
    Route::get('provedor/index',[ProvedorController::class,'index'])->middleware('access:read-ajustes,read-compra,create-compra,update-compra');
    Route::post('provedor/update',[ProvedorController::class,'update'])->middleware('access:update-ajustes');
    Route::post('provedor/store',[ProvedorController::class,'store'])->middleware('access:create-ajustes');
    Route::post('provedor/cambiar-estado',[ProvedorController::class,'changer'])->middleware('access:delete-ajustes');
    Route::post('compra/store',[CompraController::class,'store'])->middleware(['access:create-compra', 'audit-admin']);
    Route::post('compra/update',[CompraController::class,'update'])->middleware(['access:update-compra', 'audit-admin']);
    Route::post('compra/editar-estado',[CompraController::class,'editarEstado'])->middleware(['access:delete-compra', 'audit-admin']);
    Route::post('compra/index',[CompraController::class,'listarCompra'])->middleware('access:read-compra');
    Route::post('compra/show',[CompraController::class,'show'])->middleware('access:read-compra');
    Route::get('compra/{id}/edicion', [\App\Http\Controllers\OrderEditorController::class, 'show'])->middleware(['access:update-compra', 'audit-admin']);
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
    Route::post('venta/store', [VentaController::class, 'store'])
        ->middleware('access:create-venta');
    Route::get('venta', [VentaController::class, 'index'])->middleware('access:read-venta');
    Route::get('venta/{id}', [VentaController::class, 'show'])
        ->whereNumber('id')->middleware('access:read-venta');
    Route::post('venta/{id}/rectificar', [VentaController::class, 'rectificar'])
        ->whereNumber('id')->middleware('access:update-venta');
    Route::post('venta/{id}/cancelar', [VentaController::class, 'cancelar'])
        ->whereNumber('id')->middleware('access:delete-venta');
    Route::post('venta/{id}/devolver', [VentaController::class, 'devolver'])
        ->whereNumber('id')->middleware('access:update-venta');
    Route::get('venta/confirmacion/{codigo}', [VentaController::class, 'confirmar'])
        ->middleware('access:create-venta');
    Route::post('envio/store', [VentaController::class, 'store'])
        ->middleware('access:create-venta');
    Route::post('cajachica/store',[CajachicaController::class,'store'])->middleware('access:create-caja');
    Route::post('cajachica/show',[CajachicaController::class,'show'])->middleware('access:read-caja');
    Route::get('cajachica/index',[CajachicaController::class,'index'])->middleware('access:read-caja');
    Route::get('perdidas', [PerdidaController::class, 'index'])
        ->middleware('access:read-camara');
    Route::post('perdidas', [PerdidaController::class, 'store'])
        ->middleware('access:admin');
    Route::get('categorias-gasto', [OperationalController::class, 'categorias'])
        ->middleware('access:read-caja,create-caja,admin');
    Route::post('categorias-gasto', [OperationalController::class, 'guardarCategoria'])
        ->middleware('access:admin');
    Route::get('indicadores', [\App\Http\Controllers\ReportsController::class, 'indicadores'])
        ->middleware('access:read-dashboard');
    Route::get('reportes/catalogos', [\App\Http\Controllers\ReportsController::class, 'catalogos'])
        ->middleware('access:read-dashboard');
    Route::get('reportes', [\App\Http\Controllers\ReportsController::class, 'index'])
        ->middleware('access:read-dashboard');
    Route::get('historial', [OperationalController::class, 'historial'])
        ->middleware('access:admin');
    Route::post('auth/logout', [UserController::class, 'logout']);
    //vetna
    // ->middleware('access:create-venta')


    


});
