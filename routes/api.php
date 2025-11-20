<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\SeguimientoPedidoController;
use App\Http\Controllers\SeguimientoPagoController;
use App\Http\Controllers\ComisionVentaController;
use App\Http\Controllers\PedidoEstadoController;
use App\Http\Controllers\PedidoExternoController;
use App\Http\Controllers\PedidoInternoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ColeccionesController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/shopify/orders', [ShopifyController::class, 'getOrders']);
Route::get('/shopify/orders/{orderId}.json', [ShopifyController::class, 'getOrderById']);
Route::get('/shopify/products', [ShopifyController::class, 'getProducts']);
Route::get('/shopify/products/{orderId}.json', [ShopifyController::class, 'getProductById']);

Route::post('/shopify/product', [ProductoController::class, 'createProduct']);
Route::put('/shopify/productos/{id}', [ProductoController::class, 'updateProduct']);
Route::post('/shopify/product/{id}/image', [ProductoController::class, 'uploadImage']);
Route::get('/shopify/location', [ProductoController::class, 'listLocations']);
Route::delete('/shopify/products/{id}', [ProductoController::class, 'deleteProduct']);

Route::post('/delete-product-media', [ProductoController::class, 'deleteProductMedia']);
Route::post('/set-media-as-first', [ProductoController::class, 'setMediaAsFirst']);

Route::get('/product/{id}/media', [ShopifyController::class, 'getProductMedia']);
Route::get('/shopify/productos/media', [ShopifyController::class, 'getAllProductsMedia']);

Route::post('/upload-video-only', [ProductoController::class, 'uploadVideoOnly']); // Ruta para subir solo el video
Route::post('/attach-video-to-product', [ProductoController::class, 'attachVideoToProduct']);
Route::get('/shopify/product/{id}/images', [ProductoController::class, 'getProductImages']);
Route::get('/shopify/product/{id}/media', [ProductoController::class, 'getAllProductMedia']);
Route::post('/upload-image-only', [ProductoController::class, 'uploadImageOnly']);
Route::post('/attach-image-to-product', [ProductoController::class, 'attachImageToProduct']);

Route::post('/associate-video-to-product', [ProductoController::class, 'associateVideoToProduct']); // Ruta para asociar

Route::get('/inventory-levels/{inventory_item_id}', [ProductoController::class, 'getInventoryLevels']);

Route::get('/collections', [ColeccionesController::class, 'listCollections']);
Route::post('/collections', [ColeccionesController::class, 'createCollection']);
Route::put('/collections/{id}', [ColeccionesController::class, 'updateCollection']);
Route::delete('/collections/{id}', [ColeccionesController::class, 'deleteCollection']); 
Route::post('/collections/{id}/products', [ColeccionesController::class, 'addProductToCollection']);
Route::delete('/collections/{id}/products/{productId}', [ColeccionesController::class, 'removeProductFromCollection']);


//rutas del api estado de pedidos
Route::post('/estado-pedido', [PedidoEstadoController::class, 'actualizarEstado']);
Route::get('/estado-pedido/{shopify_order_id}', [PedidoEstadoController::class, 'obtenerEstado']);
Route::get('/estado-pedido-todos', [PedidoEstadoController::class, 'listarEstados']);


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

Route::get('/seguimiento-pago', [SeguimientoPagoController::class, 'index']);
Route::get('/seguimiento-pago/historial/{shopify_order_id}', [SeguimientoPagoController::class, 'historial']);
Route::get('/seguimiento-pago/ultimo/{shopify_order_id}', [SeguimientoPagoController::class, 'ultimo']);


Route::post('/seguimiento-pedido', [SeguimientoPedidoController::class, 'store']);
Route::get('/seguimiento-pedido/administracion', [SeguimientoPedidoController::class, 'getAdministracionSeguimientos']);
Route::get('/seguimiento-pedido/vendedores', [SeguimientoPedidoController::class, 'getVentasSeguimientos']);
Route::get('/seguimiento-pedido/almacen', [SeguimientoPedidoController::class, 'getAlmacenSeguimientos']);
Route::get('/seguimiento-pedido/delivery', [SeguimientoPedidoController::class, 'getDeliverySeguimientos']);
Route::get('/seguimiento-pedido/{shopify_order_id}/historial', [SeguimientoPedidoController::class, 'historial']);
Route::get('/seguimientos/ultimo-por-orden', [SeguimientoPedidoController::class, 'getUltimoSeguimientoPorOrden']);
Route::get('/seguimientos/{shopify_order_id}/ultimo', [SeguimientoPedidoController::class, 'getUltimoEstadoPorOrden']);

// Listar comisiones ventas

Route::get('/comision-ventas', [ComisionVentaController::class, 'index']);
Route::post('/comision-ventas', [ComisionVentaController::class, 'store']);
Route::put('/comision-ventas/{id}', [ComisionVentaController::class, 'update']);
Route::get('/comision-ventas/user/{userId}', [ComisionVentaController::class, 'showByUser']);


Route::post('/login', [UsuarioController::class, 'login']);
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
Route::post('/register', [UsuarioController::class, 'register']);
