<?php

namespace App\Http\Controllers;

use App\Models\SeguimientoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SeguimientoPagoController extends Controller
{

    public function index(): JsonResponse
    {
        $pagos = SeguimientoPago::with('responsable')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Listado de seguimientos de pago obtenido correctamente',
            'data' => $pagos,
        ], 200);
    }

    public function historial($shopify_order_id): JsonResponse
    {
        $historial = SeguimientoPago::where('shopify_order_id', $shopify_order_id)
            ->orderBy('created_at', 'asc')
            ->with('responsable')
            ->get();

        return response()->json([
            'message' => 'Historial de pagos obtenido correctamente',
            'data' => $historial,
        ], 200);
    }

    public function ultimo($shopify_order_id): JsonResponse
    {
        $ultimoPago = SeguimientoPago::where('shopify_order_id', $shopify_order_id)
            ->with('responsable')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$ultimoPago) {
            return response()->json([
                'message' => 'No se encontró seguimiento de pago para este pedido.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Último seguimiento de pago obtenido correctamente',
            'data' => $ultimoPago,
        ], 200);
    }
}
