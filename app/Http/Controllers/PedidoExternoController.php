<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Models\PedidoExterno;
use App\Models\PedidoExternoEnvio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoExternoController extends Controller
{
    public function storeOrUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shopify_order_id' => [
                'required',
                'integer',
                Rule::unique('pedido_externo', 'shopify_order_id')->ignore($request->shopify_order_id, 'shopify_order_id'),
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

        $pedido = PedidoExterno::updateOrCreate(
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
            'message' => 'Pedido externo guardado correctamente',
            'data' => $pedido->load('productos'),
        ], 200);
    }

    public function storeOrUpdateEnvio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shopify_order_id' => [
                'required',
                'integer',
                'exists:pedido_externo,shopify_order_id', // Asegura que el pedido externo exista
            ],
            'estado_agencial' => 'nullable|string|max:100',
            'fecha_envio' => 'nullable|date',
            'fecha_llegada' => 'nullable|date',
            'costo_envio' => 'nullable|numeric|min:0',
            'codigo_inicial' => 'nullable|string|max:100',
            'monto_pendiente' => 'nullable|numeric|min:0',
            'fecha_depositada' => 'nullable|date',
            'medio_pago' => 'nullable|string|max:100',
            'numero_operacion' => 'nullable|string|max:100',
            'notas_administrativas' => 'nullable|string',
        ]);

        $data = array_filter([
            'estado_agencial' => $validated['estado_agencial'] ?? null,
            'fecha_envio' => $validated['fecha_envio'] ?? null,
            'fecha_llegada' => $validated['fecha_llegada'] ?? null,
            'costo_envio' => $validated['costo_envio'] ?? null,
            'codigo_inicial' => $validated['codigo_inicial'] ?? null,
            'monto_pendiente' => $validated['monto_pendiente'] ?? null,
            'fecha_depositada' => $validated['fecha_depositada'] ?? null,
            'medio_pago' => $validated['medio_pago'] ?? null,
            'numero_operacion' => $validated['numero_operacion'] ?? null,
            'notas_administrativas' => $validated['notas_administrativas'] ?? null,
        ]);

        $envio = PedidoExternoEnvio::updateOrCreate(
            ['shopify_order_id' => $validated['shopify_order_id']],
            $data
        );

        return response()->json([
            'message' => 'Envío de pedido externo guardado correctamente',
            'data' => $envio,
        ], 200);
    }

    public function showByShopifyId($shopify_order_id): JsonResponse
    {
        $pedido = PedidoExterno::with(['productos', 'envio'])
            ->where('shopify_order_id', $shopify_order_id)
            ->first();

        if (!$pedido) {
            return response()->json(null, 200);
        }

        return response()->json($pedido, 200);
    }
}