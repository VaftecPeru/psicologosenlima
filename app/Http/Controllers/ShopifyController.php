<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\Producto;

class ShopifyController extends Controller
{
    protected $baseUrl;
    protected $accessToken;
    protected $apiVersion;

    public function __construct()
    {
        $this->baseUrl = "https://" . env('SHOPIFY_STORE') . "/admin/api/" . env('SHOPIFY_API_VERSION') . "/";
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiVersion = env('SHOPIFY_API_VERSION');
    }

    // Método para obtener productos
    public function getOrders()
    {
        $url = $this->baseUrl . "orders.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
        ])->get($url);

        if ($response->successful()) {
            return response()->json($response->json(), 200);
        } else {
            return response()->json([
                'error' => 'Error al consultar órdenes',
                'details' => $response->body()
            ], $response->status());
        }
    }

    public function getOrderById($orderId)
    {
        try {
            $shopDomain = env('SHOPIFY_STORE');
            $accessToken = env('SHOPIFY_ACCESS_TOKEN');

            $url = "https://{$shopDomain}/admin/api/2025-10/orders/{$orderId}.json";
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => 'No se pudo obtener el pedido'], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProducts($queryParameters = [])
    {
        try {
            $url = $this->baseUrl . "products.json";
            $defaultParams = ['limit' => 250];
            $finalParams = array_merge($defaultParams, $queryParameters);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Accept' => 'application/json',
            ])->get($url, $finalParams);

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Productos listados (máx. 250)',
                    'data' => $response->json()['products'] ?? [],
                ], 200);
            } else {

                $status = $response->status();
                return response()->json([
                    'error' => 'Error al obtener productos desde Shopify',
                    'details' => $response->body()
                ], $status);
            }
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error de conexión o inesperado',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function getProductById($productId)
    {
        try {
            $url = $this->baseUrl . "products/{$productId}.json";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $product = $response->json()['product'] ?? null;
                return response()->json([
                    'message' => "Producto #{$productId} obtenido correctamente",
                    'data' => $product
                ], 200);
            } else {
                $status = $response->status();
                $errorMsg = ($status == 404)
                    ? 'El ID del producto no fue encontrado.'
                    : 'Error al obtener producto.';

                return response()->json([
                    'error' => $errorMsg,
                    'details' => $response->body()
                ], $status);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error de conexión o inesperado',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function syncProduct($shopifyId)
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->get("{$this->baseUrl}/products/{$shopifyId}.json");

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Producto no encontrado en Shopify',
                    'shopify_id' => $shopifyId
                ], 404);
            }

            $product = $response->json('product');

            $saved = Producto::updateOrCreate(
                ['id_producto_shopify' => $product['id']],
                [
                    'titulo' => $product['title'],
                    'descripcion' => $product['body_html'] ?? null,
                    'multimedia' => $product['images'] ?? [],
                    'categoria' => $product['product_type'] ?? 'General',
                    'precio' => $product['variants'][0]['price'] ?? 0,
                    'cantidad' => $product['variants'][0]['inventory_quantity'] ?? 0,
                    'estado' => $product['status'] === 'active' ? 'activo' : 'inactivo',
                ]
            );

            return response()->json([
                'message' => 'Producto sincronizado correctamente',
                'shopify_id' => $product['id'],
                'local_id' => $saved->id,
                'titulo' => $product['title']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error sincronizando producto',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function syncAllProducts()
    {
        try {
            $limit = 250;
            $totalSincronizados = 0;
            $nextPageInfo = null; // ← DEFINIMOS DESDE EL INICIO

            do {
                $params = ['limit' => $limit];
                if ($nextPageInfo) {
                    $params['page_info'] = $nextPageInfo;
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Accept' => 'application/json',
                ])->get("{$this->baseUrl}/products.json", $params);

                if ($response->failed()) {
                    return response()->json([
                        'error' => 'Error al obtener productos de Shopify',
                        'status' => $response->status(),
                        'body' => $response->body()
                    ], 500);
                }

                $data = $response->json();
                $products = $data['products'] ?? [];

                foreach ($products as $product) {
                    try {
                        Producto::updateOrCreate(
                            ['id_producto_shopify' => $product['id']],
                            [
                                'titulo' => $product['title'] ?? 'Sin título',
                                'descripcion' => $product['body_html'] ?? null,
                                'multimedia' => $product['images'] ?? [],
                                'categoria' => $product['product_type'] ?? 'General',
                                'precio' => $product['variants'][0]['price'] ?? 0,
                                'cantidad' => $product['variants'][0]['inventory_quantity'] ?? 0,
                                'estado' => $product['status'] === 'active' ? 'activo' : 'inactivo',
                            ]
                        );
                        $totalSincronizados++;
                    } catch (\Exception $e) {
                        // Continúa con el siguiente
                    }
                }

                // === OBTENER SIGUIENTE PÁGINA ===
                $linkHeader = $response->header('Link');
                $nextPageInfo = null;

                if ($linkHeader) {
                    // Busca rel="next"
                    if (preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>; ?rel="next"/', $linkHeader, $matches)) {
                        $nextPageInfo = $matches[1];
                    }
                }
            } while ($nextPageInfo); // ← Ahora SÍ existe y no da error

            return response()->json([
                'message' => '¡Sincronización completa exitosa!',
                'total_sincronizados' => $totalSincronizados,
                'fecha' => now()->format('d/m/Y H:i:s'),
                'duración' => round(microtime(true) - LARAVEL_START, 2) . ' segundos'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error crítico en sincronización masiva',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
