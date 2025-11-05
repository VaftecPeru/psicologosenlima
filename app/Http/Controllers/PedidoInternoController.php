<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\PedidoInterno;

class PedidoInternoController extends Controller
{

    public function storeOrUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shopify_order_id' => [
                'required',
                'integer',
                Rule::unique('pedido_interno', 'shopify_order_id')->ignore($request->shopify_order_id, 'shopify_order_id'),
            ],
            'asesor' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:50',
            'codigo' => 'nullable|string|max:100',
            'celular' => 'nullable|string|max:20',
            'cliente' => 'nullable|string|max:150',
            'provincia_distrito' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:255',
            'referencias' => 'nullable|string|max:255',
            'notas_asesor' => 'nullable|string',
            'notas_supervisor' => 'nullable|string',
            'productos' => 'nullable|array',
            'productos.*.nombre' => 'required_with:productos|string|max:255',
            'productos.*.cantidad' => 'required_with:productos|integer|min:1',
            'productos.*.precio' => 'required_with:productos|numeric|min:0',
        ]);

        $data = array_filter([
            'asesor' => $validated['asesor'] ?? null,
            'estado' => $validated['estado'] ?? null,
            'codigo' => $validated['codigo'] ?? null,
            'celular' => $validated['celular'] ?? null,
            'cliente' => $validated['cliente'] ?? null,
            'provincia_distrito' => $validated['provincia_distrito'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'referencias' => $validated['referencias'] ?? null,
            'notas_asesor' => $validated['notas_asesor'] ?? null,
            'notas_supervisor' => $validated['notas_supervisor'] ?? null,
        ]);

        $pedido = PedidoInterno::updateOrCreate(
            ['shopify_order_id' => $validated['shopify_order_id']],
            $data
        );

        if (!empty($validated['productos'])) {
            $pedido->productos()->delete();
            foreach ($validated['productos'] as $producto) {
                $pedido->productos()->create([
                    'nombre_producto' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Pedido guardado correctamente',
            'data' => $pedido->load('productos'),
        ], 200);
    }


    public function showByShopifyId($shopify_order_id): JsonResponse
    {
        $pedido = PedidoInterno::with('productos')
            ->where('shopify_order_id', $shopify_order_id)
            ->first();

        if (!$pedido) {
            return response()->json(null, 200);
        }

        return response()->json($pedido, 200);
    }


}