<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ColeccionesController extends Controller
{
    /**
     * Config Shopify
     */
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

    /**
     * Listar colecciones (Custom + Smart)
     */
    public function listCollections(Request $request): JsonResponse
    {
        $config = $this->getShopifyConfig();

        $type = $request->query('type'); // custom, smart o null
        $limit = $request->query('limit', 50);
        $page = $request->query('page_info');

        $headers = [
            'X-Shopify-Access-Token' => $config['accessToken'],
        ];

        $collections = [];

        // ---------------------------
        // 1. Listar Custom Collections
        // ---------------------------
        if (!$type || $type === 'custom') {
            $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections.json";

            $customResponse = Http::withHeaders($headers)
                ->get($url, [
                    'limit' => $limit,
                    'page_info' => $page
                ]);

            if ($customResponse->successful()) {
                $collections['custom_collections'] = $customResponse->json()['custom_collections'] ?? [];
            } else {
                $collections['custom_error'] = $customResponse->json();
            }
        }

        /*
        // ---------------------------
        // 2. Listar Smart Collections
        // ---------------------------
        if (!$type || $type === 'smart') {
            $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/smart_collections.json";

            $smartResponse = Http::withHeaders($headers)
                ->get($url, [
                    'limit' => $limit,
                    'page_info' => $page
                ]);

            if ($smartResponse->successful()) {
                $collections['smart_collections'] = $smartResponse->json()['smart_collections'] ?? [];
            } else {
                $collections['smart_error'] = $smartResponse->json();
            }
        }*/

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    public function getCollectionProductCount($collectionId)
    {
        $config = $this->getShopifyConfig();
        $shop = $config['shopDomain'];
        $version = $config['apiVersion'];
        $token  = $config['accessToken'];

        $headers = [
            'X-Shopify-Access-Token' => $token
        ];

        // 1️⃣ Intentar como CUSTOM COLLECTION
        $customUrl = "https://{$shop}/admin/api/{$version}/custom_collections/{$collectionId}.json?fields=products_count";

        $customResponse = Http::withHeaders($headers)->get($customUrl);

        if ($customResponse->successful() && isset($customResponse->json()['custom_collection'])) {
            return response()->json([
                'success' => true,
                'type' => 'custom',
                'collection_id' => $collectionId,
                'count' => $customResponse->json()['custom_collection']['products_count'] ?? 0
            ]);
        }

        // 2️⃣ Intentar como SMART COLLECTION
        $smartUrl = "https://{$shop}/admin/api/{$version}/smart_collections/{$collectionId}.json?fields=products_count";

        $smartResponse = Http::withHeaders($headers)->get($smartUrl);

        if ($smartResponse->successful() && isset($smartResponse->json()['smart_collection'])) {
            return response()->json([
                'success' => true,
                'type' => 'smart',
                'collection_id' => $collectionId,
                'count' => $smartResponse->json()['smart_collection']['products_count'] ?? 0
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'No se pudo obtener la cantidad de productos (ni custom ni smart).'
        ], 400);
    }
    public function getCollectionWithProductsMedia($collectionId)
    {
        $config = $this->getShopifyConfig();

        $graphqlQuery = [
            'query' => "
        query {
          collection(id: \"gid://shopify/Collection/{$collectionId}\") {
            id
            title
            descriptionHtml
            image {
              url
              altText
              width
              height
            }
            products(first: 250) {
              nodes {
                id
                title
                productType
                media(first: 1) {
                  edges {
                    node {
                      __typename
                      ... on MediaImage {
                        image { url width height }
                      }
                      ... on Video {
                        preview { image { url } }
                        sources { url mimeType format }
                      }
                      ... on ExternalVideo {
                        embedUrl
                        preview { image { url } }
                      }
                      ... on Model3d {
                        preview { image { url } }
                      }
                    }
                  }
                }
              }
            }
          }
        }"
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

            $collectionData = $response->json()['data']['collection'] ?? null;

            if (!$collectionData) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la colección.'
                ], 404);
            }

            // Procesar productos para dejar solo la primera media
            $products = [];
            foreach ($collectionData['products']['nodes'] as $p) {
                $firstMedia = null;
                $edge = $p['media']['edges'][0] ?? null;

                if ($edge) {
                    $node = $edge['node'];
                    switch ($node['__typename']) {
                        case 'MediaImage':
                            $firstMedia = ['__typename' => 'MediaImage', 'image' => $node['image']];
                            break;
                        case 'Video':
                            $firstMedia = ['__typename' => 'Video', 'preview' => $node['preview'], 'sources' => $node['sources']];
                            break;
                        case 'ExternalVideo':
                            $firstMedia = ['__typename' => 'ExternalVideo', 'embedUrl' => $node['embedUrl'], 'preview' => $node['preview']];
                            break;
                        case 'Model3d':
                            $firstMedia = ['__typename' => 'Model3d', 'preview' => $node['preview']];
                            break;
                    }
                }

                $products[] = [
                    'id' => (int) str_replace('gid://shopify/Product/', '', $p['id']),
                    'title' => $p['title'],
                    'productType' => $p['productType'] ?? null,
                    'media' => $firstMedia ? [$firstMedia] : []
                ];
            }

            // Incluir la imagen principal de la colección
            $collectionImage = $collectionData['image'] ?? null;

            return response()->json([
                'success' => true,
                'collection' => [
                    'id' => (int) str_replace('gid://shopify/Collection/', '', $collectionData['id']),
                    'title' => $collectionData['title'],
                    'descriptionHtml' => $collectionData['descriptionHtml'] ?? null,
                    'image' => $collectionImage ? [
                        'url' => $collectionImage['url'] ?? null,
                        'altText' => $collectionImage['altText'] ?? null,
                        'width' => $collectionImage['width'] ?? null,
                        'height' => $collectionImage['height'] ?? null,
                    ] : null,
                ],
                'total_products' => count($products),
                'products' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }


    /**
     * Crear una colección manual (Custom Collection)
     */
    public function createCollection(Request $request): JsonResponse
    {
        // 1. Validamos también el campo 'products' que viene como string JSON
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'products' => 'nullable|json', // Validamos que sea un JSON válido
        ]);

        $config = $this->getShopifyConfig();

        // 2. Preparamos payload de la colección
        $payload = [
            'custom_collection' => [
                'title' => $data['titulo'],
                'body_html' => $data['descripcion'] ?? '',
            ]
        ];

        // 3. Crear colección en Shopify
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->post("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections.json", $payload);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        // Obtenemos la colección creada
        $collection = $response->json()['custom_collection'];
        $collectionId = $collection['id']; // Guardamos el ID para usarlo abajo

        // 4. Subir imagen (Tu lógica existente)
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadCollectionImage($collectionId, $request->file('image'), $config);
            if ($imageUrl) {
                $collection['image'] = ['src' => $imageUrl];
            }
        }

        // ---------------------------------------------------------
        // 5. NUEVO: Asociar productos (Lógica de Collects)
        // ---------------------------------------------------------
        if ($request->filled('products')) {
            // Decodificamos el JSON string a un array de PHP
            $productIds = json_decode($request->products, true);

            if (is_array($productIds) && count($productIds) > 0) {
                // Iteramos sobre cada ID y lo asociamos a la colección
                foreach ($productIds as $prodId) {
                    // Esta es la misma lógica que tenías en 'addProductToCollection'
                    $collectPayload = [
                        'collect' => [
                            'collection_id' => (int)$collectionId,
                            'product_id' => (int)$prodId
                        ]
                    ];

                    // Enviamos la petición a Shopify para crear el enlace (Collect)
                    // No detenemos el proceso si uno falla, pero podrías loguear errores si quisieras
                    Http::withHeaders([
                        'X-Shopify-Access-Token' => $config['accessToken'],
                        'Content-Type' => 'application/json'
                    ])->post(
                        "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/collects.json",
                        $collectPayload
                    );

                    // Opcional: Pequeña pausa para no saturar el límite de la API de Shopify si son muchos productos
                    // usleep(200000); // 0.2 segundos
                }
            }
        }
        // ---------------------------------------------------------

        return response()->json([
            'success' => true,
            'collection' => $collection,
            'message' => 'Colección creada y productos asociados correctamente'
        ]);
    }

    /**
     * Actualizar colección manual
     */
    public function updateCollection(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'titulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'image' => 'nullable|image|max:4096',
            'remove_image' => 'nullable|boolean',
        ]);

        $config = $this->getShopifyConfig();

        $payload = [
            'custom_collection' => array_filter([
                'id' => $id,
                'title' => $data['titulo'] ?? null,
                'body_html' => $data['descripcion'] ?? null,
            ])
        ];

        // Editar colección
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->put("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections/{$id}.json", $payload);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        // Eliminar imagen
        if (!empty($data['remove_image'])) {
            $this->deleteCollectionImage($id, $config);
        }

        // Subir nueva imagen
        if ($request->hasFile('image')) {
            $this->uploadCollectionImage($id, $request->file('image'), $config);
        }

        return response()->json([
            'success' => true,
            'collection' => $response->json()['custom_collection'],
        ]);
    }

    /**
     * Eliminar colección
     */
    public function deleteCollection($id): JsonResponse
    {
        $config = $this->getShopifyConfig();

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->delete("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections/{$id}.json");

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'message' => "Colección eliminada correctamente",
        ]);
    }

    private function uploadCollectionImage($collectionId, $imageFile, $config)
    {
        try {
            $base64 = base64_encode(file_get_contents($imageFile->getPathname()));

            $payload = [
                "custom_collection" => [
                    "id" => $collectionId,
                    "image" => [
                        "attachment" => $base64,
                        "filename" => $imageFile->getClientOriginalName()
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json'
            ])->put(
                "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections/{$collectionId}.json",
                $payload
            );

            if (!$response->successful()) {
                Log::error("Shopify error al subir imagen colección {$collectionId}", $response->json());
                return null;
            }

            return $response->json()['custom_collection']['image']['src'] ?? null;
        } catch (\Exception $e) {
            Log::error('Excepción al subir imagen de colección: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Borrar imagen de la colección
     */
    private function deleteCollectionImage($collectionId, $config)
    {
        try {
            $payload = [
                'custom_collection' => [
                    'id' => $collectionId,
                    'image' => null,
                ]
            ];

            Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
            ])->put("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/custom_collections/{$collectionId}.json", $payload);
        } catch (\Exception $e) {
            Log::error("Error eliminando imagen colección: " . $e->getMessage());
        }
    }

    public function addProductToCollection(Request $request, $collectionId)
    {
        $request->validate([
            'product_id' => 'required|numeric'
        ]);

        $config = $this->getShopifyConfig();

        $payload = [
            'collect' => [
                'collection_id' => (int)$collectionId,
                'product_id' => (int)$request->product_id
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type' => 'application/json'
        ])->post(
            "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/collects.json",
            $payload
        );

        if ($response->successful()) {
            return response()->json(['success' => true, 'collect' => $response->json('collect')]);
        }

        return response()->json([
            'success' => false,
            'error' => $response->json()
        ], $response->status());
    }

    public function removeProductFromCollection($collectionId, $productId)
    {
        $config = $this->getShopifyConfig();

        // Primero hay que obtener el collect ID
        $collects = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get(
            "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/collects.json",
            [
                'collection_id' => $collectionId,
                'product_id' => $productId,
                'limit' => 1
            ]
        );

        if (!$collects->successful() || empty($collects->json('collects'))) {
            return response()->json(['error' => 'Producto no está en la colección'], 404);
        }

        $collectId = $collects->json('collects.0.id');

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->delete(
            "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/collects/{$collectId}.json"
        );

        return response()->json(['success' => $response->successful()]);
    }
}
