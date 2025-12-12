<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifyAnalyticsController
{
    private $url;
    private $token;

    public function __construct()
    {
        $store = env('SHOPIFY_STORE');
        $version = env('SHOPIFY_API_VERSION', '2025-10');

        $this->url = "https://{$store}/admin/api/{$version}/graphql.json";
        $this->token = env('SHOPIFY_ACCESS_TOKEN');
    }

    public function getMetric(Request $request)
    {
        try {
            $request->validate([
                'metric' => 'required|string',
                'start'  => 'required|date',
                'end'    => 'required|date',
            ]);

            $metricKey = $request->metric;
            $startDate = $request->start;
            $endDate   = $request->end;

            $queries = $this->getQueriesMap();

            if (!isset($queries[$metricKey])) {
                throw new Exception("La métrica '{$metricKey}' no existe en el mapa de consultas.");
            }

            $config = $queries[$metricKey];

            // Query de ShopifyQL
            $shopifyQL = "FROM {$config['dataset']} "
                . "SHOW {$config['fields']} "
                . "SINCE {$startDate} "
                . "UNTIL {$endDate} "
                . "{$config['extras']}";

            $escapedQL = addslashes($shopifyQL);

            // GraphQL actualizado para Shopify 2025.10
            $graphqlQuery = "
            {
                shopifyqlQuery(query: \"{$escapedQL}\") {
                    tableData {
                        columns { name dataType }
                        rows
                    }
                    parseErrors
                }
            }";

            Log::info("QUERY SHOPIFYQL: " . $shopifyQL);
            Log::info("GRAPHQL ENVIADO: " . $graphqlQuery);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->post($this->url, [
                'query' => $graphqlQuery
            ]);

            Log::info("RESPUESTA SHOPIFY RAW: " . $response->body());

            if ($response->failed()) {
                throw new Exception("Error en Shopify: " . $response->body());
            }

            $data = $response->json();

            if (isset($data['errors'])) {
                throw new Exception(json_encode($data['errors']));
            }

            // Extraer datos y errores
            $shopifyData = $data['data']['shopifyqlQuery'] ?? [];
            $tableData   = $shopifyData['tableData'] ?? [];
            $rows        = $tableData['rows'] ?? [];
            $columns     = $tableData['columns'] ?? [];
            $errors      = $shopifyData['parseErrors'] ?? null;

            return response()->json([
                'status'  => 'success',
                'metric'  => $metricKey,
                'columns' => $columns,
                'rows'    => $rows,
                'errors'  => $errors
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function getQueriesMap(): array
    {
        return [

            'returns_over_time'         => ['dataset' => 'sales', 'fields' => 'returns', 'extras' => 'TIMESERIES day'],
            'orders_over_time'          => ['dataset' => 'sales', 'fields' => 'orders', 'extras' => 'TIMESERIES day'],
            'sessions_over_time'        => ['dataset' => 'sessions', 'fields' => 'sessions', 'extras' => 'TIMESERIES day'],
            'returning_customer_rate'   => ['dataset' => 'sales', 'fields' => 'returning_customer_rate', 'extras' => 'TIMESERIES day'],
            'conversion_rate_over_time' => ['dataset' => 'sessions', 'fields' => 'conversion_rate', 'extras' => 'TIMESERIES day'],
            'return_rate_over_time' => ['dataset' => 'sales', 'fields' => 'returns / gross_sales', 'extras' => 'TIMESERIES day'],
            'aov_over_time'             => ['dataset' => 'sales', 'fields' => 'average_order_value', 'extras' => 'TIMESERIES day'],
            'gross_sales_over_time'     => ['dataset' => 'sales', 'fields' => 'gross_sales', 'extras' => 'TIMESERIES day'],
            'total_sales_over_time'     => ['dataset' => 'sales', 'fields' => 'total_sales', 'extras' => 'TIMESERIES day'],
        ];
    }
}
