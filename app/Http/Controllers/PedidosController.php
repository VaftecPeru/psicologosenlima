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
        $store = env('SHOPIFY_STORE');
        $version = env('SHOPIFY_API_VERSION');
        $this->token = env('SHOPIFY_ACCESS_TOKEN');

        $this->baseUrl = "https://{$store}/admin/api/{$version}";
    }

    public function show($id)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->get("{$this->baseUrl}/orders/{$id}.json");

        return response()->json($response->json());
    }

    public function store(Request $request)
    {
        // VALIDACIÓN SEGURA
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

            // Campos extra personalizados
            "vendedor" => "nullable|string",
            "empresa_envio" => "nullable|string",
            "estado_confirmacion" => "nullable|string",
            "primer_abono" => "nullable|string",
            "saldo" => "nullable|string",
            "almacen" => "nullable|string",
        ]);

        // MAPEO DE CAMPOS EXTRA → NOTE ATTRIBUTES
        $noteAttributes = [];
        $extraFields = [
            "vendedor"            => "vendedor",
            "empresa_envio"       => "empresa_envio",
            "estado_confirmacion" => "confirmacion_pedido",
            "primer_abono"        => "primer_abono",
            "saldo"               => "saldo",
            "almacen"             => "almacen"
        ];

        foreach ($extraFields as $reqKey => $shopifyKey) {
            if ($request->filled($reqKey)) {
                $noteAttributes[] = [
                    "name"  => $shopifyKey,
                    "value" => $request->$reqKey
                ];
            }
        }

        // SEPARAR NOMBRE Y APELLIDO
        $fullName = $validated["shipping_address"]["name"];
        $nameParts = explode(" ", $fullName, 2);

        $customer = [
            "first_name" => $nameParts[0] ?? "Cliente",
            "last_name"  => $nameParts[1] ?? "",
            "email"      => $validated["email"]
        ];

        // CONSTRUCCIÓN DEL PAYLOAD A SHOPIFY
        $orderPayload = [
            "email"            => $validated["email"],
            "line_items"       => $validated["line_items"],
            "shipping_address" => $validated["shipping_address"],
            "customer"         => $customer,

            // 🔥 ESTO ES LO QUE FALTABA
            "financial_status" => "pending",
            "fulfillment_status" => null,
        ];

        if (!empty($validated["note"])) {
            $orderPayload["note"] = $validated["note"];
        }

        if (!empty($noteAttributes)) {
            $orderPayload["note_attributes"] = $noteAttributes;
        }

        $data = ["order" => $orderPayload];

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
        // SOLO CAMPOS QUE SHOPIFY PERMITE ACTUALIZAR
        $noteAttributes = [];

        $allowed = [
            "vendedor"             => "vendedor",
            "empresa_envio"        => "empresa_envio",
            "estado_confirmacion"  => "confirmacion_pedido",
            "primer_abono"         => "primer_abono",
            "saldo"                => "saldo",
            "almacen"              => "almacen"
        ];

        foreach ($allowed as $reqKey => $shopKey) {
            if (!empty($request->$reqKey)) {
                $noteAttributes[] = [
                    "name"  => $shopKey,
                    "value" => $request->$reqKey
                ];
            }
        }

        $data = [
            "order" => array_filter([
                "note"            => $request->note ?? null,
                "note_attributes" => !empty($noteAttributes) ? $noteAttributes : null,
            ])
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->put("{$this->baseUrl}/orders/{$id}.json", $data);

        return response()->json($response->json(), $response->status());
    }

    public function cancel($id)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/orders/{$id}/cancel.json");

        return response()->json($response->json());
    }
}
