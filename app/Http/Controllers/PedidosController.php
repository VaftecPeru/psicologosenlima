<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PedidosController extends Controller
{
    private $token;
    private $baseUrl;

    public function __construct()
    {
        $store = env('SHOPIFY_STORE');          // ← directo del .env
        $version = env('SHOPIFY_API_VERSION');   // ← directo del .env
        $this->token = env('SHOPIFY_ACCESS_TOKEN'); // ← directo del .env

        $this->baseUrl = "https://{$store}/admin/api/{$version}";
    }

    public function show($id)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type'          => 'application/json',
        ])->get("{$this->baseUrl}/orders/{$id}.json");

        return response()->json($response->json());
    }
    public function store(Request $request)
    {
        // VALIDACIÓN
        $validated = $request->validate([
            "email" => "required|email",
            "line_items" => "required|array|min:1",
            "line_items.*.variant_id" => "required|integer",
            "line_items.*.quantity" => "required|integer|min:1",

            "shipping_address" => "required|array",
            "shipping_address.address1" => "required|string",
            "shipping_address.city" => "required|string",
            "shipping_address.country" => "required|string",
            "shipping_address.name" => "required|string",

            "note" => "nullable|string",

            // Campos extra
            "vendedor" => "nullable|string",
            "empresa_envio" => "nullable|string",
            "estado_confirmacion" => "nullable|string",
            "primer_abono" => "nullable|string",
            "saldo" => "nullable|string",
            "almacen" => "nullable|string",
        ]);

        // MAPA CORRECTO DE CAMPOS
        $extraFields = [
            "vendedor"             => "vendedor",
            "empresa_envio"        => "empresa_envio",
            "estado_confirmacion"  => "confirmacion_pedido",
            "primer_abono"         => "primer_abono",
            "saldo"                => "saldo",
            "almacen"              => "almacen",
        ];

        $noteAttributes = [];

        foreach ($extraFields as $requestKey => $shopifyKey) {
            if ($request->$requestKey !== null && $request->$requestKey !== "") {
                $noteAttributes[] = [
                    "name"  => $shopifyKey,
                    "value" => $request->$requestKey
                ];
            }
        }

        // ARMANDO PAYLOAD FINAL
        $data = [
            "order" => array_filter([
                "email" => $validated["email"],
                "line_items" => $validated["line_items"],
                "note" => $validated["note"] ?? null,
                "shipping_address" => $validated["shipping_address"],
                "note_attributes" => $noteAttributes ?: null,
            ])
        ];

        // PETICIÓN A SHOPIFY
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/orders.json", $data);

        return response()->json([
            "sent_payload" => $data,
            "shopify_response" => $response->json()
        ], $response->status());
    }

    public function update(Request $request, $id)
    {
        $data = [
            "order" => [
                "id"                => $id,
                "note"              => $request->note,
                "note_attributes"   => [
                    ["name" => "vendedor",           "value" => $request->vendedor],
                    ["name" => "empresa_envio",      "value" => $request->empresa_envio],
                    ["name" => "confirmacion_pedido", "value" => $request->estado_confirmacion],
                    ["name" => "primer_abono",       "value" => $request->primer_abono],
                    ["name" => "saldo",              "value" => $request->saldo],
                    ["name" => "almacen",            "value" => $request->almacen],
                ],
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type'          => 'application/json',
        ])->put("{$this->baseUrl}/orders/{$id}.json", $data);

        return response()->json($response->json());
    }

    public function cancel($id)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type'          => 'application/json',
        ])->post("{$this->baseUrl}/orders/{$id}/cancel.json");

        return response()->json($response->json());
    }
}
