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
        // ... (Tu lógica de VALIDACIÓN y construcción de PAYLOAD - Se mantiene igual) ...

        $validated = $request->validate([
            "email" => "required|email",
            "line_items" => "required|array|min:1",
            "line_items.*.variant_id" => "required|integer",
            "line_items.*.quantity" => "required|integer|min:1",
            "shipping_address" => "required|array",
            "shipping_address.first_name"  => "required|string",
            "shipping_address.last_name"   => "nullable|string",
            "shipping_address.company"     => "nullable|string",
            "shipping_address.address1"    => "required|string",
            "shipping_address.address2"    => "nullable|string",
            "shipping_address.city"        => "required|string",
            "shipping_address.province"    => "nullable|string",
            "shipping_address.country"     => "required|string",
            "shipping_address.zip"         => "nullable|string",
            "shipping_address.phone"       => "nullable|string",
            "note" => "nullable|string",
            "vendedor" => "nullable|string",
            "empresa_envio" => "nullable|string",
            "estado_confirmacion" => "nullable|string",
            "primer_abono" => "nullable|string",
            "saldo" => "nullable|string",
            "almacen" => "nullable|string",
        ]);

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

        $customer = [
            "first_name" => $validated["shipping_address"]["first_name"],
            "last_name"  => $validated["shipping_address"]["last_name"] ?? "",
            "email"      => $validated["email"],
            "phone"      => $validated["shipping_address"]["phone"] ?? null
        ];

        $address = [
            "first_name" => $validated["shipping_address"]["first_name"],
            "last_name"  => $validated["shipping_address"]["last_name"] ?? "",
            "company"    => $validated["shipping_address"]["company"] ?? null,
            "address1"   => $validated["shipping_address"]["address1"],
            "address2"   => $validated["shipping_address"]["address2"] ?? null,
            "city"       => $validated["shipping_address"]["city"],
            "province"   => $validated["shipping_address"]["province"] ?? null,
            "country"    => $validated["shipping_address"]["country"],
            "zip"        => $validated["shipping_address"]["zip"] ?? null,
            "phone"      => $validated["shipping_address"]["phone"] ?? null
        ];

        $draftOrderPayload = [
            "email" => $validated["email"],
            "line_items" => $validated["line_items"],
            "customer" => $customer,
            "shipping_address" => $address,
            "billing_address"  => $address,
        ];

        if (!empty($validated["note"])) {
            $draftOrderPayload["note"] = $validated["note"];
        }

        if (!empty($noteAttributes)) {
            $draftOrderPayload["note_attributes"] = $noteAttributes;
        }

        $dataToSend = ["draft_order" => $draftOrderPayload];

        // --- 4. CREAR Y COMPLETAR DRAFT ORDER (POST & PUT) ---
        try {
            // CREAR DRAFT ORDER
            $createResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/draft_orders.json", $dataToSend);

            $createData = $createResponse->json();

            if (!$createResponse->successful() || !isset($createData['draft_order']['id'])) {
                return response()->json(['status' => 'error', 'message' => 'Error al crear la Draft Order.', 'error_details' => $createData], $createResponse->status());
            }

            $draftOrderId = $createData['draft_order']['id'];

            // COMPLETAR DRAFT ORDER
            $completeUrl = "{$this->baseUrl}/draft_orders/{$draftOrderId}/complete.json?payment_pending=true";

            $completeResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->put($completeUrl);

            $completeData = $completeResponse->json();

            if (!$completeResponse->successful() || !isset($completeData['order']['id'])) {
                return response()->json(['status' => 'warning', 'message' => 'Draft Order creada (ID: ' . $draftOrderId . '), pero falló al finalizar.', 'error_details' => $completeData], $completeResponse->status());
            }

            $finalOrderId = $completeData['order']['id']; // ID de la orden de venta final

            // --- 5. AÑADIDO: OBTENER DETALLE COMPLETO CON GET ---
            // Usamos tu función 'show' para obtener todos los detalles necesarios para tu aplicación.
            $detailedResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/orders/{$finalOrderId}.json");

            // Si el GET es exitoso, devolvemos la respuesta detallada
            if ($detailedResponse->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Pedido creado y detallado correctamente.',
                    'order' => $detailedResponse->json()['order'] // La orden completa y detallada
                ], 200);
            } else {
                // Si el GET falla (raro, pero posible)
                return response()->json([
                    'status' => 'partial_success',
                    'message' => 'Pedido creado, pero no se pudo obtener la vista detallada (ID: ' . $finalOrderId . ')',
                    'order' => $completeData['order'] // Devolvemos la respuesta incompleta como respaldo
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'exception', 'message' => 'Error de conexión o excepción: ' . $e->getMessage()], 500);
        }
    }


    // public function store(Request $request)
    // {
    //     // ... (Tu lógica de VALIDACIÓN y construcción de PAYLOAD - Se mantiene igual) ...

    //     $validated = $request->validate([
    //         "email" => "required|email",
    //         "line_items" => "required|array|min:1",
    //         "line_items.*.variant_id" => "required|integer",
    //         "line_items.*.quantity" => "required|integer|min:1",
    //         "shipping_address" => "required|array",
    //         "shipping_address.first_name"  => "required|string",
    //         "shipping_address.last_name"   => "nullable|string",
    //         "shipping_address.company"     => "nullable|string",
    //         "shipping_address.address1"    => "required|string",
    //         "shipping_address.address2"    => "nullable|string",
    //         "shipping_address.city"        => "required|string",
    //         "shipping_address.province"    => "nullable|string",
    //         "shipping_address.country"     => "required|string",
    //         "shipping_address.zip"         => "nullable|string",
    //         "shipping_address.phone"       => "nullable|string",
    //         "note" => "nullable|string",
    //         "vendedor" => "nullable|string",
    //         "empresa_envio" => "nullable|string",
    //         "estado_confirmacion" => "nullable|string",
    //         "primer_abono" => "nullable|string",
    //         "saldo" => "nullable|string",
    //         "almacen" => "nullable|string",
    //     ]);

    //     $noteAttributes = [];
    //     $extraFields = [
    //         "vendedor"              => "vendedor",
    //         "empresa_envio"         => "empresa_envio",
    //         "estado_confirmacion"   => "confirmacion_pedido",
    //         "primer_abono"          => "primer_abono",
    //         "saldo"                 => "saldo",
    //         "almacen"               => "almacen"
    //     ];

    //     foreach ($extraFields as $reqKey => $shopifyKey) {
    //         if ($request->filled($reqKey)) {
    //             $noteAttributes[] = [
    //                 "name"  => $shopifyKey,
    //                 "value" => $request->$reqKey
    //             ];
    //         }
    //     }

    //     $customer = [
    //         "first_name" => $validated["shipping_address"]["first_name"],
    //         "last_name"  => $validated["shipping_address"]["last_name"] ?? "",
    //         "email"      => $validated["email"],
    //         "phone"      => $validated["shipping_address"]["phone"] ?? null
    //     ];

    //     $address = [
    //         "first_name" => $validated["shipping_address"]["first_name"],
    //         "last_name"  => $validated["shipping_address"]["last_name"] ?? "",
    //         "company"    => $validated["shipping_address"]["company"] ?? null,
    //         "address1"   => $validated["shipping_address"]["address1"],
    //         "address2"   => $validated["shipping_address"]["address2"] ?? null,
    //         "city"       => $validated["shipping_address"]["city"],
    //         "province"   => $validated["shipping_address"]["province"] ?? null,
    //         "country"    => $validated["shipping_address"]["country"],
    //         "zip"        => $validated["shipping_address"]["zip"] ?? null,
    //         "phone"      => $validated["shipping_address"]["phone"] ?? null
    //     ];

    //     $draftOrderPayload = [
    //         "email" => $validated["email"],
    //         "line_items" => $validated["line_items"],
    //         "customer" => $customer,
    //         "shipping_address" => $address,
    //         "billing_address"  => $address,
    //     ];

    //     if (!empty($validated["note"])) {
    //         $draftOrderPayload["note"] = $validated["note"];
    //     }

    //     if (!empty($noteAttributes)) {
    //         $draftOrderPayload["note_attributes"] = $noteAttributes;
    //     }

    //     $dataToSend = ["draft_order" => $draftOrderPayload];

    //     // --- 4. CREAR Y COMPLETAR DRAFT ORDER (POST & PUT - REST API) ---
    //     try {
    //         // CREAR DRAFT ORDER
    //         $createResponse = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $this->token,
    //             'Content-Type' => 'application/json',
    //         ])->post("{$this->baseUrl}/draft_orders.json", $dataToSend);

    //         $createData = $createResponse->json();

    //         if (!$createResponse->successful() || !isset($createData['draft_order']['id'])) {
    //             return response()->json(['status' => 'error', 'message' => 'Error al crear la Draft Order.', 'error_details' => $createData], $createResponse->status());
    //         }

    //         $draftOrderId = $createData['draft_order']['id'];

    //         // COMPLETAR DRAFT ORDER
    //         $completeUrl = "{$this->baseUrl}/draft_orders/{$draftOrderId}/complete.json?payment_pending=true";

    //         $completeResponse = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $this->token,
    //             'Content-Type' => 'application/json',
    //         ])->put($completeUrl);

    //         $completeData = $completeResponse->json();

    //         if (!$completeResponse->successful() || !isset($completeData['order']['id'])) {
    //             return response()->json(['status' => 'warning', 'message' => 'Draft Order creada (ID: ' . $draftOrderId . '), pero falló al finalizar.', 'error_details' => $completeData], $completeResponse->status());
    //         }

    //         $finalOrderId = $completeData['order']['id']; // ID de la orden de venta final (REST ID)

    //         // --- 5. AÑADIDO: OBTENER DETALLE COMPLETO CON GRAPHQL ---
    //         // Convertir el ID de la API REST (numérico) a GID (Global ID) para GraphQL
    //         $orderGid = "gid://shopify/Order/{$finalOrderId}";

    //         $graphqlQuery = $this->buildOrderQuery($orderGid); // Usamos una función auxiliar

    //         $graphqlResponse = Http::withHeaders([
    //             // Nota: Para GraphQL es la misma cabecera de autenticación.
    //             'X-Shopify-Access-Token' => $this->token,
    //             'Content-Type' => 'application/json',
    //         ])->post("{$this->baseUrl}/graphql.json", [ // Nuevo endpoint de GraphQL
    //             'query' => $graphqlQuery,
    //         ]);

    //         $graphqlData = $graphqlResponse->json();

    //         // Si la consulta GraphQL es exitosa
    //         if ($graphqlResponse->successful() && isset($graphqlData['data']['order'])) {
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'Pedido creado y detallado correctamente (vía GraphQL).',
    //                 'order' => $graphqlData['data']['order'] // La orden completa y detallada (formato GraphQL)
    //             ], 200);
    //         } else {
    //             // Si la consulta GraphQL falla
    //             return response()->json([
    //                 'status' => 'partial_success',
    //                 'message' => 'Pedido creado, pero falló la consulta GraphQL (ID: ' . $finalOrderId . ')',
    //                 'order_rest_backup' => $completeData['order'], // Devolvemos la respuesta REST incompleta como respaldo
    //                 'graphql_error' => $graphqlData['errors'] ?? 'Sin errores detallados.'
    //             ], 200);
    //         }

    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'exception', 'message' => 'Error de conexión o excepción: ' . $e->getMessage()], 500);
    //     }
    // }

    // private function buildOrderQuery(string $orderGid): string
    // {
    //     // *** IMPORTANTE: Aquí se listan todos los campos anidados que deseas recibir ***
    //     return <<<GQL
    //         query GetDetailedOrder {
    //             order(id: "{$orderGid}") {
    //                 id
    //                 name
    //                 legacyResourceId # El ID numérico de REST
    //                 email
    //                 phone
    //                 createdAt
    //                 statusPageUrl

    //                 # Datos de Nota y Atributos (donde pusiste tus campos extra)
    //                 note
    //                 noteAttributes {
    //                     key
    //                     value
    //                 }

    //                 # Detalles del cliente
    //                 customer {
    //                     firstName
    //                     lastName
    //                     email
    //                 }

    //                 # Dirección de envío COMPLETA
    //                 shippingAddress {
    //                     firstName
    //                     lastName
    //                     address1
    //                     address2
    //                     city
    //                     province
    //                     country
    //                     zip
    //                     phone
    //                     company
    //                 }

    //                 # Artículos de la línea (Line Items)
    //                 lineItems(first: 50) {
    //                     edges {
    //                         node {
    //                             title
    //                             quantity
    //                             sku
    //                             variant {
    //                                 price
    //                             }
    //                             totalPriceSet {
    //                                 shopMoney {
    //                                     amount
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }

    //                 # Totales financieros
    //                 totalPriceSet {
    //                     shopMoney {
    //                         amount
    //                         currencyCode
    //                     }
    //                 }
    //                 totalShippingPriceSet {
    //                     shopMoney {
    //                         amount
    //                     }
    //                 }
    //                 totalTaxSet {
    //                     shopMoney {
    //                         amount
    //                     }
    //                 }
    //             }
    //         }
    //     GQL;
    // }


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

    public function updateNote(Request $request, $id)
    {
        // Validamos solo el campo note
        $validated = $request->validate([
            "note" => "nullable|string",
        ]);

        $data = [
            "order" => [
                "note" => $validated["note"] ?? null,
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->put("{$this->baseUrl}/orders/{$id}.json", $data);

        return response()->json($response->json(), $response->status());
    }

    public function updateShippingAddress(Request $request, $id)
    {
        $validated = $request->validate([
            "first_name" => "required|string",
            "last_name"  => "nullable|string",
            "company"    => "nullable|string",
            "address1"   => "required|string",
            "address2"   => "nullable|string",
            "city"       => "required|string",
            "province"   => "nullable|string",
            "country"    => "required|string",
            "zip"        => "nullable|string",
            "phone"      => "nullable|string",
        ]);

        $data = [
            "order" => [
                "shipping_address" => [
                    "first_name" => $validated["first_name"],
                    "last_name"  => $validated["last_name"] ?? "",
                    "company"    => $validated["company"] ?? null,
                    "address1"   => $validated["address1"],
                    "address2"   => $validated["address2"] ?? null,
                    "city"       => $validated["city"],
                    "province"   => $validated["province"] ?? null,
                    "country"    => $validated["country"],
                    "zip"        => $validated["zip"] ?? null,
                    "phone"      => $validated["phone"] ?? null,
                ]
            ]
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->put("{$this->baseUrl}/orders/{$id}.json", $data);

        return response()->json($response->json(), $response->status());
    }
}
