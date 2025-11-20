<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;


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

    private function getShopifyConfig(): array
    {
        $shopDomain = env('SHOPIFY_STORE');
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $apiVersion = env('SHOPIFY_API_VERSION', '2025-10');

        if (!$shopDomain || !$accessToken) {
            abort(response()->json([
                'success' => false,
                'error' => 'Faltan variables en el archivo .env',
            ], 500));
        }

        return compact('shopDomain', 'accessToken', 'apiVersion');
    }

    public function getProductMedia($productId): JsonResponse
    {
        $config = $this->getShopifyConfig();
        $gid = "gid://shopify/Product/{$productId}";

        $graphqlQuery = [
            'query' => "
        query {
            product(id: \"{$gid}\") {
                media(first: 1) { 
                    edges {
                        node {
                            id
                            __typename
                            mediaContentType

                            ... on MediaImage {
                                image {
                                    url
                                    width
                                    height
                                }
                            }

                            ... on Video {
                                sources {
                                    url
                                    mimeType
                                }
                            }

                            ... on ExternalVideo {
                                embedUrl
                            }

                            ... on Model3d {
                                sources { url }
                            }
                        }
                    }
                }
            }
        }
        "
        ];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json',
            ])->post(
                "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/graphql.json",
                $graphqlQuery
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => $response->json(),
                ], $response->status());
            }

            $edges = $response->json()['data']['product']['media']['edges'] ?? [];
            $media = array_map(fn($item) => $item["node"], $edges);

            return response()->json([
                'success' => true,
                'media' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAllProductsMedia(): JsonResponse
    {
        $config = $this->getShopifyConfig();

        $graphqlQuery = [
            'query' => "
            query {
                products(first: 250) {
                    nodes {
                        id
                        title
                        productType
                        media(first: 100) {
                            edges {
                                node {
                                    id
                                    alt
                                    __typename
                                    mediaContentType

                                    ... on MediaImage {
                                        image {
                                            url
                                            width
                                            height
                                        }
                                    }

                                    ... on Video {
                                        preview {
                                            image {
                                                url(transform: { maxWidth: 100, maxHeight: 100 })
                                            }
                                        }
                                        sources {
                                            url
                                            mimeType
                                            format
                                        }
                                    }

                                    ... on ExternalVideo {
                                        embedUrl
                                        preview {
                                            image {
                                                url
                                            }
                                        }
                                    }

                                    ... on Model3d {
                                        preview {
                                            image {
                                                url
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        "
        ];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json',
            ])->post(
                "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/graphql.json",
                $graphqlQuery
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => $response->json(),
                ], $response->status());
            }

            $nodes = $response->json()['data']['products']['nodes'] ?? [];
            $productos = [];

            foreach ($nodes as $p) {
                $allMedia = [];

                foreach ($p['media']['edges'] as $edge) {
                    $node = $edge['node'];
                    $media = null;

                    if ($node['__typename'] === 'MediaImage' && $node['image']) {
                        $media = [
                            'id' => $node['id'], // <-- agregamos el ID de la media
                            '__typename' => 'MediaImage',
                            'image' => [
                                'url' => $node['image']['url']
                            ]
                        ];
                    }

                    if ($node['__typename'] === 'Video') {
                        $previewImage = $node['preview']['image']['url'] ?? null;

                        $media = [
                            'id' => $node['id'],
                            '__typename' => 'Video',
                            'preview' => [
                                'image' => [
                                    'url' => $previewImage
                                ]
                            ],
                            'sources' => $node['sources'] ?? []
                        ];
                    }

                    if ($node['__typename'] === 'ExternalVideo') {
                        $previewImage = $node['preview']['image']['url'] ?? null;
                        $media = [
                            'id' => $node['id'],
                            '__typename' => 'ExternalVideo',
                            'preview' => [
                                'image' => [
                                    'url' => $previewImage
                                ]
                            ],
                            'embedUrl' => $node['embedUrl']
                        ];
                    }

                    if ($media) {
                        $allMedia[] = $media;
                    }
                }

                $productos[] = [
                    'id' => (int) str_replace('gid://shopify/Product/', '', $p['id']),
                    'title' => $p['title'],
                    'productType' => $p['productType'] ?? null,
                    'media' => $allMedia // ahora devuelve todos los medios
                ];
            }

            return response()->json([
                'success' => true,
                'total' => count($productos),
                'productos' => $productos
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
