<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    protected $shopifyUrl;
    protected $accessToken;

    public function __construct()
    {
        $store = env('SHOPIFY_STORE');
        $version = env('SHOPIFY_API_VERSION', '2024-10');
        $this->shopifyUrl = "https://{$store}/admin/api/{$version}";
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    private function findProducto($id)
    {
        // Si el ID es numérico y mayor a 1000000000 → probablemente es ID de Shopify
        if (is_numeric($id) && $id > 1000000000) {
            return Producto::where('id_producto_shopify', $id)->first();
        }

        // Sino, buscar por ID local
        return Producto::find($id);
    }

    // === 1. LISTAR PRODUCTOS ===
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query();

        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where('titulo', 'LIKE', "%{$term}%")
                ->orWhere('id_producto_shopify', 'LIKE', "%{$term}%")
                ->orWhere('id', $term); // también por ID local
        }

        $productos = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Productos obtenidos',
            'data' => $productos,
            'total' => $productos->count()
        ]);
    }

    // === 2. CREAR PRODUCTO EN SHOPIFY + DB LOCAL ===
    public function store(Request $request): JsonResponse
    {
        // === 1. VALIDACIÓN ===
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096', // <= imagen directa
            'categoria' => 'nullable|string|max:255',
            'precio' => 'required|numeric|min:0',
            'cantidad' => 'required|integer|min:0',
            'estado' => 'required|string|in:activo,inactivo,agotado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // === 2. PREPARAR DATOS DE IMAGEN ===
            $imagenesPayload = [];
            if ($request->hasFile('imagen')) {
                $file = $request->file('imagen');
                $base64 = base64_encode(file_get_contents($file->getRealPath()));

                $imagenesPayload[] = [
                    'attachment' => $base64,
                    'filename' => $file->getClientOriginalName(),
                ];
            }

            // === 3. CREAR PRODUCTO EN SHOPIFY (CON IMAGEN INCLUIDA) ===
            $shopifyStatus = $request->estado === 'activo' ? 'active' : 'draft';

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->shopifyUrl}/products.json", [
                'product' => [
                    'title' => $request->titulo,
                    'body_html' => $request->descripcion ?? '',
                    'product_type' => $request->categoria ?? 'General',
                    'status' => $shopifyStatus,
                    'variants' => [[
                        'price' => $request->precio,
                        'inventory_quantity' => $request->cantidad,
                        'inventory_management' => 'shopify',
                    ]],
                    'images' => $imagenesPayload, // <= Se envía la imagen base64 aquí
                ]
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Error al crear producto en Shopify',
                    'error' => $response->json()
                ], $response->status()); // Usar el código de estado de Shopify
            }

            $shopifyProduct = $response->json('product');
            $shopifyId = $shopifyProduct['id'];

            // === 4. GUARDAR EN BASE DE DATOS LOCAL ===
            // (Asegúrate de que tu modelo Producto tenga 'fillable' estos campos)
            $producto = Producto::create([
                'id_producto_shopify' => $shopifyId,
                'titulo' => $shopifyProduct['title'],
                'descripcion' => $shopifyProduct['body_html'] ?? null,
                // Shopify devuelve la lista de imágenes, usamos la primera si existe
                'multimedia' => $shopifyProduct['images'] ? collect($shopifyProduct['images'])->pluck('src')->toArray() : [],
                'categoria' => $shopifyProduct['product_type'] ?? null,
                'precio' => $shopifyProduct['variants'][0]['price'],
                'cantidad' => $shopifyProduct['variants'][0]['inventory_quantity'],
                'estado' => $request->estado, // Usar el estado local
            ]);

            // === 5. RESPUESTA FINAL ===
            return response()->json([
                'message' => 'Producto creado exitosamente en Shopify y guardado localmente',
                'data' => $producto,
                'shopify_id' => $shopifyId
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general al crear producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // === 4. ACTUALIZAR (por ID local o Shopify) ===
    public function update(Request $request, $id): JsonResponse
    {
        $producto = $this->findProducto($id);

        if (!$producto || !$producto->id_producto_shopify) {
            return response()->json([
                'message' => 'Producto no encontrado o sin ID de Shopify',
                'searched_id' => $id
            ], 404);
        }

        // === 1. VALIDACIÓN ===
        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string|max:255',
            'precio' => 'sometimes|required|numeric|min:0',
            'cantidad' => 'sometimes|required|integer|min:0',
            'estado' => 'sometimes|required|string|in:activo,inactivo,agotado',

            // Lógica de 'store' aplicada: Aceptamos un archivo nuevo.
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',

            // Lógica original de 'update': Aceptamos un array de URLs.
            'multimedia' => 'nullable|array',
            'multimedia.*' => 'url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // === 2. PREPARAR DATOS DE IMAGEN ===
            // (Damos prioridad al archivo 'imagen' si se envía)

            $imagenesPayload = null;

            if ($request->hasFile('imagen')) {
                // Lógica de 'store': subir archivo nuevo
                $file = $request->file('imagen');
                $base64 = base64_encode(file_get_contents($file->getRealPath()));
                $imagenesPayload = [
                    [ // Shopify espera un array de imágenes
                        'attachment' => $base64,
                        'filename' => $file->getClientOriginalName(),
                    ]
                ];
            } elseif ($request->has('multimedia')) {
                // Lógica original de 'update': usar URLs
                $imagenesPayload = array_map(fn($url) => ['src' => $url], $request->multimedia);
            }

            // === 3. PREPARAR Y ACTUALIZAR PRODUCTO EN SHOPIFY ===
            $shopifyStatus = ($request->estado ?? $producto->estado) === 'activo' ? 'active' : 'draft';

            $data = [
                'product' => [
                    'id' => $producto->id_producto_shopify,
                    'title' => $request->filled('titulo') ? $request->titulo : $producto->titulo,
                    'body_html' => $request->filled('descripcion') ? $request->descripcion : $producto->descripcion,
                    'product_type' => $request->filled('categoria') ? $request->categoria : $producto->categoria,
                    'status' => $shopifyStatus,
                    'variants' => [[
                        // Para actualizar una variante existente, es mejor tener su ID.
                        // Si solo tienes una variante por producto, esto podría fallar
                        // o crear una nueva variante y dejar la antigua.
                        // Para ser robusto, deberías guardar y usar el 'variant_id'.
                        // Por simplicidad, asumimos que se actualiza la primera variante.
                        // NOTA: Una mejor práctica es enviar solo los campos que cambian.
                        'id' => $producto->id_variante_shopify ?? null, // Necesitarías guardar esto
                        'price' => $request->filled('precio') ? $request->precio : $producto->precio,
                        'inventory_quantity' => $request->filled('cantidad') ? $request->cantidad : $producto->cantidad,
                    ]],
                ]
            ];

            // Añadir las imágenes solo si se proporcionó 'imagen' o 'multimedia'
            // Enviar 'images: []' borraría todas las imágenes.
            if (!is_null($imagenesPayload)) {
                $data['product']['images'] = $imagenesPayload;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->put("{$this->shopifyUrl}/products/{$producto->id_producto_shopify}.json", $data);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Error al actualizar producto en Shopify',
                    'error' => $response->json()
                ], $response->status());
            }

            $shopifyProduct = $response->json('product');

            // === 4. ACTUALIZAR BASE DE DATOS LOCAL ===
            // Usamos la respuesta de Shopify para sincronizar.

            // Primero, preparamos los datos que SÍ vienen del request
            $localUpdateData = $request->only('titulo', 'descripcion', 'categoria', 'precio', 'cantidad', 'estado');

            // Sobrescribimos con los datos confirmados por Shopify
            $localUpdateData['titulo'] = $shopifyProduct['title'];
            $localUpdateData['descripcion'] = $shopifyProduct['body_html'] ?? null;
            $localUpdateData['categoria'] = $shopifyProduct['product_type'] ?? null;
            $localUpdateData['precio'] = $shopifyProduct['variants'][0]['price'];
            $localUpdateData['cantidad'] = $shopifyProduct['variants'][0]['inventory_quantity'];
            $localUpdateData['estado'] = $request->filled('estado') ? $request->estado : $producto->estado; // Shopify status es 'active'/'draft'

            // Sincronizamos las imágenes desde la respuesta de Shopify
            $localUpdateData['multimedia'] = $shopifyProduct['images'] ? collect($shopifyProduct['images'])->pluck('src')->toArray() : [];

            $producto->update($localUpdateData);

            // === 5. RESPUESTA FINAL ===
            return response()->json([
                'message' => 'Producto actualizado en Shopify y localmente',
                'data' => $producto->fresh(),
                'updated_by' => is_numeric($id) && $id > 1000000000 ? 'ID Shopify' : 'ID Local'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general al actualizar producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // === 3. MOSTRAR UN PRODUCTO (por ID local o Shopify) ===
    public function show($id): JsonResponse
    {
        $producto = $this->findProducto($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado',
                'searched_id' => $id
            ], 404);
        }

        return response()->json([
            'message' => 'Producto encontrado',
            'data' => $producto,
            'id_local' => $producto->id,
            'id_shopify' => $producto->id_producto_shopify
        ]);
    }
    // === 5. ELIMINAR (por ID local o Shopify) ===
    public function destroy($id): JsonResponse
    {
        $producto = $this->findProducto($id);

        if (!$producto || !$producto->id_producto_shopify) {
            return response()->json([
                'message' => 'Producto no encontrado o sin ID de Shopify',
                'searched_id' => $id
            ], 404);
        }

        try {
            Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->delete("{$this->shopifyUrl}/products/{$producto->id_producto_shopify}.json");
        } catch (\Exception $e) {
            // No falla si ya estaba eliminado
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado de Shopify y base de datos',
            'deleted_by' => is_numeric($id) && $id > 1000000000 ? 'ID Shopify' : 'ID Local'
        ]);
    }
}
