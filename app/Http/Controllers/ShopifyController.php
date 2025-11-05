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
}
