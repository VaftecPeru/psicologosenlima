<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\Producto;
use App\Models\ProductoVariante;

class ProductoController extends Controller
{
    /**
     * Obtener configuración Shopify desde .env (reutilizable)
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
     * Listar ubicaciones disponibles (para el front)
     */
    public function listLocations(): JsonResponse
    {
        $config = $this->getShopifyConfig();

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/locations.json");

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        $locations = array_filter($response->json()['locations'] ?? [], fn($loc) => $loc['active'] ?? false);

        return response()->json([
            'success' => true,
            'locations' => array_values($locations),
        ]);
    }
    /**
     * Crear producto con categoría (product_type), tags separados, y foto por variante (opcional)
     */
    public function createProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'product_type'     => 'nullable|string|max:255',
            'tags'             => 'nullable|string',
            'precio'           => 'required_without:variantes|numeric|min:0',
            'cantidad'         => 'required_without:variantes|integer|min:0',
            'estado'           => 'nullable|string|max:50',
            'multimedia'       => 'nullable|image|max:4096',
            'sku' => 'nullable|string|max:255',
            'variantes'        => 'nullable|array',
            'variantes.*.option1'     => 'required_with:variantes|string',
            'variantes.*.option2'     => 'nullable|string',
            'variantes.*.option3'     => 'nullable|string',
            'variantes.*.price'       => 'required_with:variantes|numeric|min:0',
            'variantes.*.cantidad'    => 'required_with:variantes|integer|min:0',
            'variantes.*.multimedia'  => 'nullable|image|max:4096',
            'variantes.*.sku' => 'nullable|string|max:255',


            'location_id'      => 'required|string',
        ]);

        $config = $this->getShopifyConfig();
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products.json";

        // Producto
        $payload = [
            'product' => [
                'title'        => $data['titulo'],
                'body_html'    => $data['descripcion'] ?? '',
                'product_type' => $data['product_type'] ?? '',
                'tags'         => $data['tags'] ?? '',
                'status'       => $data['estado'] ?? 'active',
            ],
        ];

        // Variantes
        if (!empty($data['variantes'])) {
            $options = [];

            // 🔹 Nombres personalizados enviados desde el front
            $optionNames = $request->input('option_names', []);

            foreach (['option1', 'option2', 'option3'] as $index => $optKey) {
                $values = array_filter(array_column($data['variantes'], $optKey));

                if (!empty($values)) {
                    // Usa el nombre del front si existe, o un nombre genérico si no
                    $name = $optionNames[$index + 1] ?? ucfirst(str_replace('option', 'Opción ', $optKey));

                    $options[] = [
                        'name'   => $name,
                        'values' => array_values(array_unique($values)),
                    ];
                }
            }

            $payload['product']['options'] = $options;
            $payload['product']['variants'] = [];

            foreach ($data['variantes'] as $var) {
                $payload['product']['variants'][] = [
                    'option1' => $var['option1'] ?? null,
                    'option2' => $var['option2'] ?? null,
                    'option3' => $var['option3'] ?? null,
                    'price'   => (string) $var['price'],
                    'inventory_management' => 'shopify',
                    'sku' => $var['sku'] ?? null,
                ];
            }
        } else {
            // Caso de variante única por defecto: agregar precio y sku al payload
            $payload['product']['variants'] = [
                [
                    'price' => (string) ($data['precio'] ?? 0),
                    'sku' => $data['sku'] ?? null,
                    'inventory_management' => 'shopify',
                ]
            ];
        }


        // Crear producto
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        $product = $response->json()['product'];

        // Actualizar inventario
        if (!empty($data['variantes'])) {
            foreach ($product['variants'] as $index => $variant) {
                $cantidad = $data['variantes'][$index]['cantidad'] ?? null;
                if ($cantidad !== null) {
                    $updateResponse = $this->updateInventory($variant['inventory_item_id'], $cantidad, $data['location_id'], $config);
                    if (!$updateResponse->successful()) {
                        Log::error('Error actualizando inventario variante: ' . json_encode($updateResponse->json()));
                    }
                }
            }
        } elseif (isset($product['variants'][0]) && isset($data['cantidad'])) {
            $updateResponse = $this->updateInventory($product['variants'][0]['inventory_item_id'], $data['cantidad'], $data['location_id'], $config);
            if (!$updateResponse->successful()) {
                Log::error('Error actualizando inventario simple: ' . json_encode($updateResponse->json()));
            }
        }

        // Subir foto principal (si existe)
        if ($request->hasFile('multimedia')) {
            $this->uploadImageToProduct($request->file('multimedia'), $product['id'], $config);
        }

        // Subir foto por variante (si existe)
        if (!empty($data['variantes'])) {
            foreach ($data['variantes'] as $index => $var) {
                if ($request->hasFile("variantes.{$index}.multimedia")) {
                    $variantId = $product['variants'][$index]['id'];
                    $this->uploadImageToVariant($request->file("variantes.{$index}.multimedia"), $product['id'], $variantId, $config);
                }
            }
        }

        // Sincronizar a BD local
        try {
            $this->sincronizarProductoEnBd($product['id']);
        } catch (\Exception $e) {
            Log::error('Error sincronizando producto en BD: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error guardando en base de datos local: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }
    /**
     * Actualizar inventario en Shopify
     */
    private function updateInventory($inventoryItemId, $cantidad, $locationId, $config)
    {
        if (!$locationId) return null;

        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/inventory_levels/set.json";

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type' => 'application/json',
        ])->post($url, [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available' => (int) $cantidad,
        ]);
    }
    /**
     * Subir imagen al producto (principal)
     */
    private function uploadImageToProduct($file, $productId, $config)
    {
        // 1. Obtener imágenes actuales del producto
        $imagesUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$productId}/images.json";
        $imagesResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get($imagesUrl);

        if ($imagesResponse->successful()) {
            $images = $imagesResponse->json()['images'] ?? [];

            // 2. Encontrar y eliminar la imagen principal actual (la primera o con position 1)
            $mainImage = collect($images)->firstWhere('position', 1) ?? $images[0] ?? null;
            if ($mainImage) {
                $deleteUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$productId}/images/{$mainImage['id']}.json";
                $deleteResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $config['accessToken'],
                ])->delete($deleteUrl);

                if (!$deleteResponse->successful()) {
                    Log::error('Error eliminando imagen principal anterior: ' . json_encode($deleteResponse->json()));
                }
            }
        } else {
            Log::error('Error obteniendo imágenes actuales: ' . json_encode($imagesResponse->json()));
        }

        // 3. Subir la nueva imagen (se convertirá en principal por default si no hay otras)
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$productId}/images.json";
        $imageBase64 = base64_encode(file_get_contents($file->path()));

        $payload = [
            'image' => [
                'attachment' => $imageBase64,
                'position' => 1, // Forzamos posición 1 para que sea principal
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Error subiendo nueva imagen principal: ' . json_encode($response->json()));
        }
    }
    /**
     * Subir imagen a una variante específica
     */
    private function uploadImageToVariant($file, $productId, $variantId, $config)
    {
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$productId}/images.json";
        $imageBase64 = base64_encode(file_get_contents($file->path()));

        $payload = [
            'image' => [
                'attachment' => $imageBase64,
                'variant_ids' => [$variantId], // Asocia la imagen a la variante
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Error subiendo imagen a variante: ' . json_encode($response->json()));
        }
    }

    public function uploadVideoOnly(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:102400', // hasta 100MB
        ]);

        $config = $this->getShopifyConfig();
        $videoFile = $request->file('video');

        try {
            // 1️⃣ Crear staged upload target
            $stagedUploadMutation = [
                'query' => '
        mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
            stagedUploadsCreate(input: $input) {
                stagedTargets {
                    url
                    resourceUrl
                    parameters { name value }
                }
                userErrors { field message }
            }
        }',
                'variables' => [
                    'input' => [[
                        'filename' => $videoFile->getClientOriginalName(),
                        'mimeType' => $videoFile->getMimeType(),
                        'fileSize' => (string) $videoFile->getSize(), // ⚠️ string
                        'resource' => 'VIDEO',
                    ]]
                ]
            ];

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json'
            ])->post("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/graphql.json", $stagedUploadMutation);

            $data = $response->json();
            $userErrors = $data['data']['stagedUploadsCreate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                return response()->json(['success' => false, 'step' => 'stagedUploadsCreate', 'error' => $userErrors, 'full_response' => $data], 500);
            }

            $stagedTarget = $data['data']['stagedUploadsCreate']['stagedTargets'][0] ?? null;
            if (!$stagedTarget) {
                return response()->json(['success' => false, 'step' => 'stagedUploadsCreate', 'error' => 'No se pudo crear target', 'full_response' => $data], 500);
            }

            // 2️⃣ Subir archivo al URL temporal - Corregido: estructura como array de partes
            $multipartData = [];
            foreach ($stagedTarget['parameters'] as $param) {
                $multipartData[] = [
                    'name' => $param['name'],
                    'contents' => $param['value'],
                ];
            }
            $multipartData[] = [
                'name' => 'file',
                'contents' => fopen($videoFile->path(), 'r'),
                'filename' => $videoFile->getClientOriginalName(),
            ];

            $uploadResponse = Http::asMultipart()->post($stagedTarget['url'], $multipartData);
            if (!$uploadResponse->successful()) {
                return response()->json(['success' => false, 'step' => 'upload_to_target', 'error' => $uploadResponse->body()], 500);
            }

            // 3️⃣ Registrar el vídeo con fileCreate
            $fileCreateMutation = [
                'query' => '
        mutation fileCreate($files: [FileCreateInput!]!) {
            fileCreate(files: $files) {
                files { id fileStatus }
                userErrors { field message }
            }
        }',
                'variables' => [
                    'files' => [[
                        'originalSource' => $stagedTarget['resourceUrl'],
                        'contentType' => 'VIDEO'
                    ]]
                ]
            ];

            $fileResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json'
            ])->post("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/graphql.json", $fileCreateMutation);

            $fileData = $fileResponse->json();
            $fileErrors = $fileData['data']['fileCreate']['userErrors'] ?? [];
            if (!empty($fileErrors)) {
                return response()->json(['success' => false, 'step' => 'fileCreate', 'error' => $fileErrors, 'full_response' => $fileData], 500);
            }

            // Return the resourceUrl for later association
            return response()->json([
                'success' => true,
                'resource_url' => $stagedTarget['resourceUrl'],
                'file_data' => $fileData['data']['fileCreate']
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'step' => 'exception', 'error' => $e->getMessage()], 500);
        }
    }
    public function associateVideoToProduct(Request $request): JsonResponse
    {
        $request->validate([
            'resource_url' => 'required|string',
            'product_id' => 'required|integer',
        ]);

        $config = $this->getShopifyConfig();
        $productId = $request->input('product_id');
        $resourceUrl = $request->input('resource_url');

        try {
            // 4️⃣ Asociar vídeo al producto usando resourceUrl
            $mediaMutation = [
                'query' => '
        mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
            productCreateMedia(productId: $productId, media: $media) {
                media { id status ... on Video { sources { url } } }
                userErrors { field message }
            }
        }',
                'variables' => [
                    'productId' => "gid://shopify/Product/{$productId}",
                    'media' => [[
                        'originalSource' => $resourceUrl, // ✅ resourceUrl from upload
                        'mediaContentType' => 'VIDEO',
                        'alt' => 'Video del producto'
                    ]]
                ]
            ];

            $mediaResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['accessToken'],
                'Content-Type' => 'application/json'
            ])->post("https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/graphql.json", $mediaMutation);

            $mediaData = $mediaResponse->json();
            $mediaErrors = $mediaData['data']['productCreateMedia']['userErrors'] ?? [];
            if (!empty($mediaErrors)) {
                return response()->json(['success' => false, 'step' => 'productCreateMedia', 'error' => $mediaErrors, 'full_response' => $mediaData], 500);
            }

            return response()->json(['success' => true, 'data' => $mediaData['data']['productCreateMedia']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'step' => 'exception', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request, $id): JsonResponse
    {
        // Validación básica + variantes e imágenes
        $validator = Validator::make($request->all(), [
            'titulo'       => 'required|string|max:255',
            'descripcion'  => 'nullable|string',
            'product_type' => 'nullable|string|max:255',  // Unifiqué a 'product_type' para consistencia con create
            'tags'         => 'nullable|string|max:255',
            'estado'       => 'nullable|string|in:active,draft,archived',
            'location_id'  => 'nullable|string',
            'main_image'   => 'nullable|image|max:4096', // imagen principal
            'variants'     => 'nullable|string', // JSON string desde frontend
            'option_names' => 'nullable|string',
            'variant_images.*' => 'nullable|image|max:4096', // array de imágenes por variante
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $config = $this->getShopifyConfig();

        // 🔹 Fetch del producto actual para preservar options si no se envían nuevos nombres
        $currentProductUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$id}.json";
        $currentResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get($currentProductUrl);

        if (!$currentResponse->successful()) {
            return response()->json([
                'success' => false,
                'error'   => 'No se pudo obtener el producto actual para actualización',
            ], $currentResponse->status());
        }

        $currentProduct = $currentResponse->json()['product'] ?? null;
        if (!$currentProduct) {
            return response()->json([
                'success' => false,
                'error'   => 'Producto no encontrado en Shopify',
            ], 404);
        }

        // Parsear variantes si se envían
        $variantsData = $request->filled('variants') ? json_decode($request->input('variants'), true) : [];
        if (!is_array($variantsData)) {
            return response()->json([
                'success' => false,
                'error' => 'Formato inválido para variantes',
            ], 422);
        }

        // Validar el array de variantes (similar a create)
        $variantsValidator = Validator::make(['variantes' => $variantsData], [
            'variantes'        => 'nullable|array',
            'variantes.*.id'   => 'nullable|integer', // Para updates
            'variantes.*.option1' => 'nullable|string',
            'variantes.*.option2' => 'nullable|string',
            'variantes.*.option3' => 'nullable|string',
            'variantes.*.price' => 'nullable|numeric|min:0',
            'variantes.*.inventory_quantity' => 'nullable|integer|min:0',
            'variantes.*.sku' => 'nullable|string|max:255',
        ]);

        if ($variantsValidator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $variantsValidator->errors(),
            ], 422);
        }

        // Payload básico
        $payload = [
            'product' => [
                'id'          => $id,
                'title'       => $data['titulo'],
                'body_html'   => $data['descripcion'] ?? '',
                'product_type' => $data['product_type'] ?? '',
                'tags'        => $data['tags'] ?? '',
                'status'      => $data['estado'] ?? 'active',
            ]
        ];

        // Si hay variantes, construir options y variants para el payload
        if (!empty($variantsData)) {
            $options = [];

            // 🔹 Nombres personalizados: si no se envían, usa los actuales de Shopify
            $optionNames = $request->filled('option_names')
                ? json_decode($request->input('option_names'), true)
                : [];
            if (empty($optionNames) && !empty($currentProduct['options'])) {
                foreach ($currentProduct['options'] as $index => $opt) {
                    $optionNames[$index + 1] = $opt['name'];
                }
            }

            foreach (['option1', 'option2', 'option3'] as $index => $optKey) {
                $values = array_filter(array_column($variantsData, $optKey));

                if (!empty($values)) {
                    // Usa nombre del front/actual o genérico como fallback
                    $name = $optionNames[$index + 1] ?? ucfirst(str_replace('option', 'Opción ', $optKey));

                    $options[] = [
                        'name'   => $name,
                        'values' => array_values(array_unique($values)),
                    ];
                }
            }

            $payload['product']['options'] = $options;
            $payload['product']['variants'] = [];

            foreach ($variantsData as $var) {
                $variantPayload = [
                    'option1' => $var['option1'] ?? null,
                    'option2' => $var['option2'] ?? null,
                    'option3' => $var['option3'] ?? null,
                    'price'   => (string) ($var['price'] ?? 0),
                    'sku'     => $var['sku'] ?? null,
                    'inventory_management' => 'shopify',
                ];

                if (isset($var['id'])) {
                    $variantPayload['id'] = $var['id']; // Para update
                }

                $payload['product']['variants'][] = $variantPayload;
            }
        }

        // Actualizar producto en Shopify (incluye variantes y options si enviadas)
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$id}.json";
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
            'Content-Type'            => 'application/json',
        ])->put($url, $payload);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error'   => $response->json(),
            ], $response->status());
        }

        $updatedProduct = $response->json()['product'] ?? null;

        // Subir imagen principal si viene
        if ($request->hasFile('main_image')) {
            $this->uploadImageToProduct($request->file('main_image'), $id, $config);
        }

        // Actualizar inventario si hay variantes enviadas y location_id
        $locationId = $data['location_id'] ?? null;
        if (!empty($variantsData) && $locationId) {
            foreach ($updatedProduct['variants'] as $index => $variant) {
                // Asumimos orden coincide; usa index para mapear quantity de variantsData
                $cantidad = $variantsData[$index]['inventory_quantity'] ?? 0;
                $updateResponse = $this->updateInventory($variant['inventory_item_id'], $cantidad, $locationId, $config);
                if (!$updateResponse->successful()) {
                    Log::error('Error actualizando inventario variante: ' . json_encode($updateResponse->json()));
                }
            }
        }

        // Subir imágenes de variantes si vienen (por índice)
        $variantImages = $request->file('variant_images') ?? [];
        if (!empty($variantImages)) {
            foreach ($variantImages as $index => $file) {
                if ($file) {
                    $variantId = $updatedProduct['variants'][$index]['id'] ?? null;
                    if ($variantId) {
                        $this->uploadImageToVariant($file, $id, $variantId, $config);
                    } else {
                        Log::warning("No se encontró ID de variante para imagen en índice {$index}");
                    }
                }
            }
        }

        // Sincronizar a BD local
        $this->sincronizarProductoEnBd($id);

        return response()->json([
            'success'             => true,
            'message'             => 'Producto actualizado correctamente',
            'producto_actualizado' => $updatedProduct,
        ]);
    }
    /**
     * Obtener niveles de inventario de un producto junto con los nombres de las sucursales.
     */
    public function getInventoryLevels($inventoryItemId): JsonResponse
    {
        $config = $this->getShopifyConfig();

        // 1️⃣ Obtener niveles de inventario del producto
        $inventoryUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/inventory_levels.json";

        $inventoryResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get($inventoryUrl, [
            'inventory_item_ids' => $inventoryItemId,
        ]);

        if (!$inventoryResponse->successful()) {
            return response()->json([
                'success' => false,
                'error' => $inventoryResponse->json(),
            ], $inventoryResponse->status());
        }

        $inventoryLevels = $inventoryResponse->json()['inventory_levels'] ?? [];

        // 2️⃣ Obtener lista de ubicaciones (para cruzar nombres)
        $locationsUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/locations.json";

        $locationsResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get($locationsUrl);

        if (!$locationsResponse->successful()) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudieron obtener las ubicaciones',
            ], $locationsResponse->status());
        }

        $locations = collect($locationsResponse->json()['locations'] ?? [])
            ->mapWithKeys(fn($loc) => [$loc['id'] => $loc['name']]);

        // 3️⃣ Combinar inventario + nombre de la ubicación
        $combined = array_map(function ($level) use ($locations) {
            return [
                'inventory_item_id' => $level['inventory_item_id'],
                'location_id'       => $level['location_id'],
                'location_name'     => $locations[$level['location_id']] ?? 'Desconocida',
                'available'         => $level['available'],
                'updated_at'        => $level['updated_at'],
            ];
        }, $inventoryLevels);

        // 4️⃣ Devolver resultado combinado
        return response()->json([
            'success' => true,
            'inventory_levels' => $combined,
        ]);
    }
    public function deleteProduct($id): JsonResponse
    {
        $config = $this->getShopifyConfig();

        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$id}.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->delete($url);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
            ], $response->status());
        }

        // Eliminar de BD local
        $this->eliminarProductoEnBd($id);

        return response()->json([
            'success' => true,
            'message' => "Producto {$id} eliminado correctamente",
        ]);
    }
    /**
     * Sincronizar producto de Shopify a la base de datos local
     */
    private function sincronizarProductoEnBd($shopifyProductId): void
    {
        $config = $this->getShopifyConfig();
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$shopifyProductId}.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['accessToken'],
        ])->get($url);

        if (!$response->successful()) {
            Log::error('Error sincronizando producto desde Shopify: ' . json_encode($response->json()));
            return;
        }

        $productData = $response->json()['product'] ?? null;
        if (!$productData) {
            Log::error('No se encontró datos del producto en la respuesta de Shopify.');
            return;
        }

        // Obtener locacion_id desde inventory_levels de la primera variante (si existe)
        $locacion_id = null;
        if (!empty($productData['variants'])) {
            $inventoryItemId = $productData['variants'][0]['inventory_item_id'] ?? null;
            if ($inventoryItemId) {
                $inventoryUrl = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/inventory_levels.json";
                $inventoryResponse = Http::withHeaders([
                    'X-Shopify-Access-Token' => $config['accessToken'],
                ])->get($inventoryUrl, [
                    'inventory_item_ids' => $inventoryItemId,
                ]);

                if ($inventoryResponse->successful()) {
                    $levels = $inventoryResponse->json()['inventory_levels'] ?? [];
                    if (!empty($levels)) {
                        $locacion_id = $levels[0]['location_id'] ?? null; // Toma el primero; ajusta si necesitas otro criterio
                    }
                } else {
                    Log::error('Error obteniendo inventory_levels para locacion_id: ' . json_encode($inventoryResponse->json()));
                }
            }
        }

        // Mapear datos del producto
        $producto = Producto::updateOrCreate(
            ['shopify_product_id' => $productData['id']],
            [
                'titulo' => $productData['title'],
                'descripcion' => $productData['body_html'] ?? null,
                'tipo_producto' => $productData['product_type'] ?? null,
                'tags' => $productData['tags'] ?? [],
                'estado' => $productData['status'] ?? null,
                'locacion_id' => $locacion_id,
                'url_media' => $productData['image']['src'] ?? null, // Imagen principal
            ]
        );

        // Sincronizar variantes
        foreach ($productData['variants'] as $variantData) {
            ProductoVariante::updateOrCreate(
                ['shopify_variant_id' => $variantData['id']],
                [
                    'producto_id' => $producto->id,
                    'opcion1' => $variantData['option1'] ?? null,
                    'opcion2' => $variantData['option2'] ?? null,
                    'opcion3' => $variantData['option3'] ?? null,
                    'precio' => $variantData['price'] ?? null,
                    'cantidad' => $variantData['inventory_quantity'] ?? null,
                    'sku' => $variantData['sku'] ?? null,
                    'url_media' => $variantData['image_id'] ? $this->getVariantImageSrc($productData['images'], $variantData['image_id']) : null,
                ]
            );
        }

        // Limpiar variantes eliminadas en Shopify (opcional, para mantener sync)
        $shopifyVariantIds = array_column($productData['variants'], 'id');
        ProductoVariante::where('producto_id', $producto->id)
            ->whereNotIn('shopify_variant_id', $shopifyVariantIds)
            ->delete();
    }

    /**
     * Obtener URL de imagen de variante (helper)
     */
    private function getVariantImageSrc($images, $imageId): ?string
    {
        foreach ($images as $image) {
            if ($image['id'] == $imageId) {
                return $image['src'] ?? null;
            }
        }
        return null;
    }

    /**
     * Eliminar producto de la base de datos local
     */
    private function eliminarProductoEnBd($shopifyProductId): void
    {
        $producto = Producto::where('shopify_product_id', $shopifyProductId)->first();
        if ($producto) {
            $producto->delete(); // Cascade eliminará variantes
        } else {
            Log::warning("Producto con shopify_product_id {$shopifyProductId} no encontrado en BD local.");
        }
    }
}
