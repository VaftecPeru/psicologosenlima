<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\PedidoEstadoController;
use App\Http\Controllers\NotificacionAlmacenController;
use App\Http\Controllers\PedidoInternoController;
use App\Http\Controllers\PedidoExternoController;

use App\Http\Controllers\SeguimientoPedidoController;
use App\Http\Controllers\SeguimientoPagoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ComisionVentaController;



Route::get('/shopify/orders', [ShopifyController::class, 'getOrders']);
Route::get('/shopify/locations', [ShopifyController::class, 'obtenerUbicaciones']);
Route::post('/shopify/fulfill/{orderId}', [ShopifyController::class, 'fulfillOrder']);
Route::get('/shopify/orders/{orderId}.json', [ShopifyController::class, 'getOrderById']);
Route::post('/shopify/pedidos/{id}/pagar', [ShopifyController::class, 'marcarComoPagado']);


//rutas del api estado de pedidos
Route::post('/estado-pedido', [PedidoEstadoController::class, 'actualizarEstado']);
Route::get('/estado-pedido/{shopify_order_id}', [PedidoEstadoController::class, 'obtenerEstado']);
Route::get('/estado-pedido-todos', [PedidoEstadoController::class, 'listarEstados']);

//rutas del api notificaciones
Route::post('/notificaciones/almacen', [NotificacionAlmacenController::class, 'store']);
Route::get('/notificaciones/almacen', [NotificacionAlmacenController::class, 'index']);
Route::post('/notificaciones/almacen/{id}/leido', [NotificacionAlmacenController::class, 'marcarLeido']);

// Rutas para pedidos interno

Route::post('/pedido-interno', [PedidoInternoController::class, 'storeOrUpdate']);
Route::put('/pedido-interno/{shopify_order_id}', [PedidoInternoController::class, 'storeOrUpdate']);
Route::get('/pedido-interno/shopify/{shopify_order_id}', [PedidoInternoController::class, 'showByShopifyId']);

// Rutas para pedidos externo
Route::post('/pedido-externo', [PedidoExternoController::class, 'storeOrUpdate']);
Route::put('/pedido-externo/{shopify_order_id}', [PedidoExternoController::class, 'storeOrUpdate']);
Route::post('/pedido-externo-envio', [PedidoExternoController::class, 'storeOrUpdateEnvio']);
Route::put('/pedido-externo-envio/{shopify_order_id}', [PedidoExternoController::class, 'storeOrUpdateEnvio']);
Route::get('/pedido-externo/shopify/{shopify_order_id}', [PedidoExternoController::class, 'showByShopifyId']);


Route::post('/seguimiento-pedido', [SeguimientoPedidoController::class, 'store']);
Route::get('/seguimiento-pedido/administracion', [SeguimientoPedidoController::class, 'getAdministracionSeguimientos']);
Route::get('/seguimiento-pedido/vendedores', [SeguimientoPedidoController::class, 'getVentasSeguimientos']);
Route::get('/seguimiento-pedido/almacen', [SeguimientoPedidoController::class, 'getAlmacenSeguimientos']);
Route::get('/seguimiento-pedido/delivery', [SeguimientoPedidoController::class, 'getDeliverySeguimientos']);
Route::get('/seguimiento-pedido/{shopify_order_id}/historial', [SeguimientoPedidoController::class, 'historial']);

Route::get('/seguimiento-pago', [SeguimientoPagoController::class, 'index']);
Route::get('/seguimiento-pago/historial/{shopify_order_id}', [SeguimientoPagoController::class, 'historial']);

Route::post('/login', [UsuarioController::class, 'login']);

Route::middleware('auth.token')->group(function () {

    // LOGOUT
    Route::post('/logout', [UsuarioController::class, 'logout']);
    Route::get('/usuario', [UsuarioController::class, 'showAuthUser']);
    
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/vendedores', [UsuarioController::class, 'vendedores']);
    Route::get('/usuarios/almacen', [UsuarioController::class, 'almacen']);
    Route::get('/usuarios/delivery', [UsuarioController::class, 'delivery']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
    Route::post('/usuarios/reset-password', [UsuarioController::class, 'resetPassword']);
});

// Listar comisiones ventas

Route::get('/comision-ventas', [ComisionVentaController::class, 'index']);
Route::post('/comision-ventas', [ComisionVentaController::class, 'store']);
Route::put('/comision-ventas/{id}', [ComisionVentaController::class, 'update']);
Route::get('/comision-ventas/user/{userId}', [ComisionVentaController::class, 'showByUser']);