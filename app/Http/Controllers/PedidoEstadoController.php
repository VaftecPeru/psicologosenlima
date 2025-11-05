<?php

namespace App\Http\Controllers;

use App\Models\EstadoPedido;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class PedidoEstadoController extends Controller
{
    public function listarEstados() {
        try {
            $estados = EstadoPedido::all();
            return response()->json([
                'data' => $estados,
                'message' => 'Se listó Estados de los pedidos correctamente',
                'error' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => null,
                'error' => 'Error al listar los estados de los pedidos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarEstado(Request $request){
        $validated = $request->validate([
            'shopify_order_id' => 'required|numeric',
            'estado_pago' => 'nullable|in:pagado,pendiente',
            'estado_preparacion' => 'nullable|in:preparado,no_preparado',
        ]);

        $pedido = EstadoPedido::updateOrCreate(
            ['shopify_order_id' => $validated['shopify_order_id']],
            array_filter([
                'estado_pago' => $validated['estado_pago'] ?? null,
                'estado_preparacion' => $validated['estado_preparacion'] ?? null,
            ])
        );

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'data' => $pedido,
        ], 200);
    }

    public function obtenerEstado($shopify_order_id){
        $pedido = EstadoPedido::where('shopify_order_id', $shopify_order_id)->first();

        if (!$pedido) {
            return response()->json([
                'message' => 'No se encontró el pedido',
            ], 404);
        }

        return response()->json($pedido);
    }
}
