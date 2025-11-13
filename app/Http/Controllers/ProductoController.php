<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
    public function updateProduct(Request $request, $id): JsonResponse
    {
        // Validación básica + variantes e imágenes
        $validator = Validator::make($request->all(), [
            'titulo'       => 'required|string|max:255',
            'descripcion'  => 'nullable|string',
            'productType'  => 'nullable|string|max:255',
            'tags'         => 'nullable|string|max:255',
            'estado'       => 'nullable|string|in:active,draft,archived',
            'location_id'  => 'nullable|string',
            'main_image'   => 'nullable|image|max:4096', // imagen principal
            'variants'     => 'nullable|string', // JSON string desde frontend
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
        $url = "https://{$config['shopDomain']}/admin/api/{$config['apiVersion']}/products/{$id}.json";

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
                'product_type' => $data['productType'] ?? '',
                'tags'        => $data['tags'] ?? '',
                'status'      => $data['estado'] ?? 'active',
            ]
        ];

        // Si hay variantes, construir options y variants para el payload
        if (!empty($variantsData)) {
            $options = [];

            // 🔹 Nombres personalizados (si se envían desde front; por ahora usa genéricos)
            $optionNames = $request->input('option_names', []); // Agrega al frontend si necesitas

            foreach (['option1', 'option2', 'option3'] as $index => $optKey) {
                $values = array_filter(array_column($variantsData, $optKey));

                if (!empty($values)) {
                    // Usa nombre del front o genérico
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

        return response()->json([
            'success' => true,
            'message' => "Producto {$id} eliminado correctamente",
        ]);
    }
}
