<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


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

    public function getAllProductsMediaFirst(): JsonResponse
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
                    media(first: 1) {  # <-- solo pedimos 1 media
                        edges {
                            node {
                                id
                                __typename
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

            $nodes = $response->json()['data']['products']['nodes'] ?? [];
            $productos = [];

            foreach ($nodes as $p) {
                $firstMedia = null;
                $edge = $p['media']['edges'][0] ?? null;

                if ($edge) {
                    $node = $edge['node'];

                    switch ($node['__typename']) {
                        case 'MediaImage':
                            if ($node['image']) {
                                $firstMedia = [
                                    'id' => $node['id'],
                                    '__typename' => 'MediaImage',
                                    'image' => ['url' => $node['image']['url']]
                                ];
                            }
                            break;

                        case 'Video':
                            $firstMedia = [
                                'id' => $node['id'],
                                '__typename' => 'Video',
                                'preview' => ['image' => ['url' => $node['preview']['image']['url'] ?? null]],
                                'sources' => $node['sources'] ?? []
                            ];
                            break;

                        case 'ExternalVideo':
                            $firstMedia = [
                                'id' => $node['id'],
                                '__typename' => 'ExternalVideo',
                                'preview' => ['image' => ['url' => $node['preview']['image']['url'] ?? null]],
                                'embedUrl' => $node['embedUrl']
                            ];
                            break;

                        case 'Model3d':
                            $firstMedia = [
                                'id' => $node['id'],
                                '__typename' => 'Model3d',
                                'preview' => ['image' => ['url' => $node['preview']['image']['url'] ?? null]]
                            ];
                            break;
                    }
                }

                $productos[] = [
                    'id' => (int) str_replace('gid://shopify/Product/', '', $p['id']),
                    'title' => $p['title'],
                    'productType' => $p['productType'] ?? null,
                    'media' => $firstMedia ? [$firstMedia] : []
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

    // Exportar data

    public function getExportProducts()
    {
        $allProducts = [];
        $url = $this->baseUrl . "products.json?limit=250"; // Máximo permitido por la API
        $nextPage = true;
        $sinceId = 0; // Usamos since_id para paginar

        while ($nextPage) {
            // 1. Realizar la solicitud a la API de Shopify
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->get($url . ($sinceId ? "&since_id=$sinceId" : ""));

            // 2. Manejo de errores de la solicitud
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Error al consultar productos',
                    'details' => $response->body()
                ], $response->status());
            }

            $products = $response->json()['products'] ?? [];

            // 3. Condición de salida de la paginación
            if (empty($products)) {
                $nextPage = false;
                break;
            }

            // 4. Mapeo de la data de Shopify al formato de importación
            foreach ($products as $product) {

                // Recolectar nombres de opciones a nivel de producto para usarlos en las variantes.
                // Ejemplo: ['Color', 'Talla', 'Material']
                $optionNames = array_column($product['options'] ?? [], 'name');

                $productEntry = [
                    // Campos de Producto
                    "Handle" => $product['handle'] ?? "",
                    "Title" => $product['title'] ?? "",
                    "Body_HTML" => $product['body_html'] ?? "",
                    "Vendor" => $product['vendor'] ?? "",
                    "Product_Category" => $product['product_type'] ?? "",
                    "Type" => $product['product_type'] ?? "",
                    // Convertir la cadena de tags separada por comas a un array
                    "Tags" => isset($product['tags']) && is_string($product['tags']) ? explode(',', $product['tags']) : [],
                    "Published" => !empty($product['published_at']), // Si published_at existe, está publicado

                    // Las opciones se reconstruirán en la importación, no necesitamos esta estructura compleja aquí.
                    "Options" => [],

                    "Variants" => array_map(function ($variant) use ($optionNames) {
                        $variantOptions = [];
                        // Mapear option1, option2, option3 (valores) a la estructura que espera la importación.
                        foreach (['option1', 'option2', 'option3'] as $i => $optKey) {
                            if (isset($variant[$optKey]) && !empty($variant[$optKey])) {
                                $variantOptions[] = [
                                    // Usamos el nombre del producto (ej: 'Color') y el valor de la variante (ej: 'Rojo')
                                    "Name" => $optionNames[$i] ?? 'Opción ' . ($i + 1),
                                    "Value" => $variant[$optKey],
                                    "Linked_To" => "" // Placeholder
                                ];
                            }
                        }

                        return [
                            "SKU" => $variant['sku'] ?? "",
                            "Grams" => $variant['grams'] ?? 0,
                            "Inventory_Tracker" => $variant['inventory_management'] ?? "",
                            "Inventory_Policy" => $variant['inventory_policy'] ?? "",
                            "Fulfillment_Service" => $variant['fulfillment_service'] ?? "",
                            "Price" => isset($variant['price']) ? floatval($variant['price']) : 0,
                            "Compare_At_Price" => isset($variant['compare_at_price']) ? floatval($variant['compare_at_price']) : null,
                            "Requires_Shipping" => $variant['requires_shipping'] ?? true,
                            "Taxable" => $variant['taxable'] ?? true,
                            // Unit Price fields (as Metafields in import, placeholder here)
                            "Unit_Price_Total_Measure" => 0,
                            "Unit_Price_Total_Measure_Unit" => "",
                            "Unit_Price_Base_Measure" => 0,
                            "Unit_Price_Base_Measure_Unit" => "",
                            "Barcode" => $variant['barcode'] ?? "",
                            "Image" => $variant['image_id'] ?? null, // El ID de la imagen, no la URL
                            "Weight_Unit" => $variant['weight_unit'] ?? "",
                            "Tax_Code" => $variant['tax_code'] ?? "",
                            "Cost_per_Item" => isset($variant['cost']) ? floatval($variant['cost']) : null,
                            "Options" => $variantOptions, // 👈 CRÍTICO: Las opciones están en la variante
                        ];
                    }, $product['variants'] ?? []),

                    "Images" => array_map(function ($img, $index) {
                        return [
                            "Src" => $img['src'] ?? "",
                            "Position" => $index,
                            "Alt_Text" => $img['alt'] ?? ""
                        ];
                    }, $product['images'] ?? [], array_keys($product['images'] ?? [])),

                    "Gift_Card" => $product['gift_card'] ?? false,
                    "SEO" => [
                        "Title" => $product['metafields_global_title'] ?? "", // Shopify API v2023-04 en adelante usa metafields para esto
                        "Description" => $product['metafields_global_description'] ?? ""
                    ],
                    // 🛑 Los Metafields deben obtenerse con una llamada adicional, o se dejan vacíos para mantener coherencia.
                    "Metafields" => [],
                    "Status" => $product['status'] ?? ""
                ];

                $allProducts[] = $productEntry;
            }

            // 5. Preparar la paginación para la siguiente solicitud
            $lastProduct = end($products);
            $sinceId = $lastProduct['id'] ?? 0;

            // Si el número de productos devueltos es menor que el límite, hemos llegado al final.
            $nextPage = count($products) === 250;
        }

        return response()->json($allProducts, 200);
    }

    // importar data

    public function importProductsFull(Request $request)
    {

        set_time_limit(900);
        ini_set('max_execution_time', 900);

        $products = $request->all();
        if (!is_array($products)) {
            return response()->json(['error' => 'La data debe ser un array de productos'], 400);
        }

        $processedProducts = [];
        $errors = [];

        // Iteración sobre cada producto del payload
        foreach ($products as $productData) {

            $productTitle = $productData['Title'] ?? 'Producto Desconocido';
            $productHandle = $productData['Handle'] ?? null;

            // 🛑 Validación Crítica: El Handle es necesario para el Upsert
            if (empty($productHandle)) {
                $errors[] = ['product' => $productTitle, 'reason' => 'Handle no proporcionado. Se omitirá el producto.'];
                continue;
            }

            // 1️⃣ Buscar si el producto ya existe por handle (Shopify API GET)
            $searchUrl = $this->baseUrl . "products.json?handle=" . urlencode($productHandle);
            $searchResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken
            ])->get($searchUrl);

            $existingProduct = null;
            if ($searchResponse->successful() && !empty($searchResponse->json()['products'])) {
                $existingProduct = $searchResponse->json()['products'][0];
            }

            // 2️⃣ Preparar payload para Shopify
            $tags = $productData['Tags'] ?? '';

            // 💡 CORRECCIÓN DE TAGS: Convertir array de Tags a cadena separada por comas (filtrando tags vacíos)
            if (is_array($tags)) {
                $tags = implode(', ', array_filter($tags));
            }

            $shopifyProduct = [
                "product" => [
                    "title" => $productTitle,
                    "handle" => $productHandle,
                    "body_html" => $productData['Body_HTML'] ?? '',
                    "vendor" => $productData['Vendor'] ?? '',
                    "product_type" => $productData['Product_Category'] ?? '',
                    "tags" => $tags,
                    "status" => ($productData['Published'] ?? true) ? 'active' : 'draft',
                    "options" => [],
                    "variants" => [],
                    "images" => []
                ]
            ];

            // --- 2.1 Lógica de Opciones (Nivel Producto) ---
            $productOptions = [];
            $optionNames = [];

            foreach ($productData['Variants'] ?? [] as $variant) {
                foreach ($variant['Options'] ?? [] as $i => $opt) {
                    $optionName = $opt['Name'] ?? 'Opción ' . ($i + 1);
                    $optionValue = $opt['Value'] ?? null;

                    if (!in_array($optionName, $optionNames)) {
                        $optionNames[] = $optionName;
                    }

                    if ($optionValue) {
                        if (!isset($productOptions[$optionName])) {
                            $productOptions[$optionName] = [];
                        }
                        if (!in_array($optionValue, $productOptions[$optionName])) {
                            $productOptions[$optionName][] = $optionValue;
                        }
                    }
                }
            }

            // Asignar opciones al producto (máximo 3)
            foreach ($productOptions as $name => $values) {
                if (count($shopifyProduct['product']['options']) < 3) {
                    $shopifyProduct['product']['options'][] = [
                        "name" => $name,
                        "values" => $values
                    ];
                }
            }
            // --- Fin Lógica de Opciones ---


            // --- 2.2 Lógica de Variantes (Nivel Variante) ---
            foreach ($productData['Variants'] ?? [] as $variant) {

                // Asignación de option1, option2, option3 (hasta 3)
                $variantOptions = [];
                foreach ($variant['Options'] ?? [] as $i => $opt) {
                    if ($i < 3) {
                        $variantOptions['option' . ($i + 1)] = $opt['Value'] ?? null;
                    }
                }

                $newVariant = [
                    // Campos requeridos por Shopify
                    "sku" => $variant['SKU'] ?? '',
                    "price" => $variant['Price'] ?? 0,

                    // Asignar optionX
                    "option1" => $variantOptions['option1'] ?? null,
                    "option2" => $variantOptions['option2'] ?? null,
                    "option3" => $variantOptions['option3'] ?? null,

                    // Otros campos
                    "compare_at_price" => $variant['Compare_At_Price'] ?? null,
                    "grams" => $variant['Grams'] ?? 0,
                    "inventory_management" => $variant['Inventory_Tracker'] ?? null,
                    "inventory_policy" => $variant['Inventory_Policy'] ?? null,
                    "fulfillment_service" => $variant['Fulfillment_Service'] ?? 'manual',
                    "requires_shipping" => $variant['Requires_Shipping'] ?? true,
                    "taxable" => $variant['Taxable'] ?? true,
                    "barcode" => $variant['Barcode'] ?? '',
                    "weight_unit" => $variant['Weight_Unit'] ?? 'g',
                    "cost" => $variant['Cost_per_Item'] ?? null
                ];

                // **CRÍTICO para ACTUALIZACIÓN:** Si el producto existe, buscar el ID de la variante.
                if ($existingProduct && isset($existingProduct['variants'])) {

                    // 💡 LÓGICA SIMPLIFICADA: Prioridad 1=SKU, Prioridad 2=Opciones (si SKU vacío/no match)
                    $existingVariant = collect($existingProduct['variants'])
                        ->first(function ($v) use ($newVariant) {

                            // Prioridad 1: Coincidencia por SKU (si existe y no es vacío)
                            if (!empty($newVariant['sku']) && $newVariant['sku'] === $v['sku']) {
                                return true;
                            }

                            // Prioridad 2: Coincidencia por combinación de opciones (si el SKU es vacío)
                            return empty($newVariant['sku']) &&
                                $newVariant['option1'] === $v['option1'] &&
                                $newVariant['option2'] === $v['option2'] &&
                                $newVariant['option3'] === $v['option3'];
                        });

                    if ($existingVariant) {
                        // ✅ Si se encuentra, incluimos el ID de Shopify en el payload de la variante
                        $newVariant['id'] = $existingVariant['id'];
                    }
                }

                $shopifyProduct['product']['variants'][] = $newVariant;
            }
            // --- Fin Lógica de Variantes ---

            // --- 2.3 Lógica de Imágenes ---
            foreach ($productData['Images'] ?? [] as $img) {
                $shopifyProduct['product']['images'][] = [
                    "src" => $img['Src'] ?? '',
                    "alt" => $img['Alt_Text'] ?? ''
                ];
            }


            // 3️⃣ Crear (POST) o actualizar (PUT) producto en Shopify
            if ($existingProduct) {
                $url = $this->baseUrl . "products/{$existingProduct['id']}.json";
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json'
                ])->put($url, $shopifyProduct);
            } else {
                $url = $this->baseUrl . "products.json";
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json'
                ])->post($url, $shopifyProduct);
            }

            // Manejo de respuesta
            if (!$response->successful()) {
                $errors[] = [
                    'product' => $productTitle,
                    'method' => $existingProduct ? 'PUT' : 'POST',
                    'status' => $response->status(),
                    'details' => $response->json() ?? $response->body()
                ];
                continue;
            }

            $createdProduct = $response->json()['product'];

            // 4️⃣ Guardar Metafields

            // 💡 CORRECCIÓN SEGURIDAD: Usar null-safe access para la primera variante.
            $firstVariant = $productData['Variants'][0] ?? null;

            // Prepara los campos extra a guardar como Metafields
            $extraFields = array_merge(
                $productData['Metafields'] ?? [],
                [
                    // Usar $firstVariant de forma segura.
                    'Unit_Price_Total_Measure' => $firstVariant['Unit_Price_Total_Measure'] ?? null,
                    'Unit_Price_Total_Measure_Unit' => $firstVariant['Unit_Price_Total_Measure_Unit'] ?? null,
                    'Unit_Price_Base_Measure' => $firstVariant['Unit_Price_Base_Measure'] ?? null,
                    'Unit_Price_Base_Measure_Unit' => $firstVariant['Unit_Price_Base_Measure_Unit'] ?? null,
                    'SEO_Title' => $productData['SEO']['Title'] ?? null,
                    'SEO_Description' => $productData['SEO']['Description'] ?? null,
                ]
            );

            foreach ($extraFields as $key => $value) {
                // Solo intentamos crear el Metafield si hay un valor que no sea nulo/vacío
                if (!empty($value) || $value === 0) {

                    // Determine el tipo de dato
                    $metafieldType = 'single_line_text_field';
                    $metaValue = $value;

                    if (is_array($value) || is_object($value)) {
                        $metafieldType = 'json';
                        $metaValue = json_encode($value);
                    } elseif (is_numeric($value)) {
                        $metafieldType = strpos((string)$value, '.') !== false ? 'number_float' : 'number_integer';
                    }

                    $metafieldResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $this->accessToken,
                        'Content-Type' => 'application/json'
                    ])->post($this->baseUrl . "products/{$createdProduct['id']}/metafields.json", [
                        'metafield' => [
                            'namespace' => 'custom',
                            'key' => $key,
                            'value' => $metaValue,
                            'type' => $metafieldType
                        ]
                    ]);

                    // Opcional: Manejo de errores de metafields
                    if (!$metafieldResponse->successful()) {
                        // Considerar usar un logger: Log::warning(...)
                    }
                }
            }

            $processedProducts[] = $createdProduct;
        }

        // 5️⃣ Devolver respuesta final consolidada
        if (!empty($errors)) {
            // Devolver 200 OK indicando éxito en el proceso, pero con detalles de los errores.
            return response()->json([
                'success' => true,
                'count_processed' => count($processedProducts),
                'count_errors' => count($errors),
                'errors' => $errors,
                'products' => $processedProducts,
                'message' => 'Importación finalizada. Algunos productos tuvieron errores.'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'count' => count($processedProducts),
            'products' => $processedProducts
        ], 201);
    }
}
