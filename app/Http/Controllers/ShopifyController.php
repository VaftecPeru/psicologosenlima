<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

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
}
