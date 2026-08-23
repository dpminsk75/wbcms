<?php

namespace app\components;

use yii\httpclient\Client;
use Yii;

class WbApi {
    private $baseUrl = 'https://seller-analytics-api.wildberries.ru';
/*
    public function getFunnelHistory($nmIds, $dateFrom, $dateTo) {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $client = new Client();
        
        // Подготавливаем данные
        $payload = [
            'selectedPeriod' => [
                'start' => $dateFrom,
                'end' => $dateTo
            ],
            'nmIds' => array_map('intval', $nmIds), // Принудительно в числа
            'aggregationLevel' => 'day'
        ];
//            'timezone' => 'Europe/Moscow',

        $request = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($this->baseUrl . '/api/analytics/v3/sales-funnel/products/history')
            ->addHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->setData($payload);

        try {
            $response = $request->send();

            echo "<h4>--- DEBUG: ОТВЕТ HTTP ---</h4>";
            echo "<b>Код статуса:</b> " . $response->statusCode . "<br>";
            echo "<b>Заголовки ответа:</b> <pre>" . print_r($response->headers->toArray(), true) . "</pre>";
            echo "<b>Сырой Body (Raw JSON):</b> <pre>" . ($response->content ?: 'ПУСТО') . "</pre>";

            if ($response->isOk) {
                $decoded = json_decode($response->content, true);
                return $decoded ?? null;
            }
            
            return null;

        } catch (\Exception $e) {
            echo "<h4 style='color:red;'>--- ИСКЛЮЧЕНИЕ ---</h4>";
            echo "<b>Сообщение:</b> " . $e->getMessage();
            return null;
        }
    }
*/

public function getFunnelHistory($nmIds, $dateFrom, $dateTo) {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $client = new Client();
        
        $payload = [
            'selectedPeriod' => [
                'start' => $dateFrom,
                'end' => $dateTo
            ],
            'nmIds' => array_map('intval', $nmIds),
            'aggregationLevel' => 'day'
        ];

        echo "\n[DEBUG] URL: " . $this->baseUrl . "/api/analytics/v3/sales-funnel/products/history\n";
        echo "[DEBUG] Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

        $request = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($this->baseUrl . '/api/analytics/v3/sales-funnel/products/history')
            ->addHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->setData($payload);

        try {
            $response = $request->send();
            
            // ВЫВОД ОТЛАДКИ В КОНСОЛЬ
            if (!$response->isOk) {
                echo "\n[API ERROR] HTTP Status: " . $response->statusCode . "\n";
                echo "[API ERROR] Response Body: " . $response->content . "\n";
            } else {
                echo "\n[API SUCCESS] Status: " . $response->statusCode . "\n";
            }

            if ($response->isOk) {
                return $response->data; // Используем $response->data, который сам декодирует JSON
            }
            
            return null;

        } catch (\Exception $e) {
            echo "\n[API EXCEPTION] " . $e->getMessage() . "\n";
            return null;
        }
    }


    public function getFunnelDataByDate($nmIds, $date) {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $client = new Client();
        
        $payload = [
            'selectedPeriod' => [
                'start' => $date,
                'end' => $date
            ],
            'nmIds' => array_map('intval', $nmIds),
        ];

        $request = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($this->baseUrl . '/api/analytics/v3/sales-funnel/products') // Эндпоинт без /history
            ->addHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->setData($payload);

        try {
            $response = $request->send();
            if ($response->isOk) {
                return $response->data;
            }
            echo "\n[API ERROR] Status: " . $response->statusCode . " Body: " . $response->content . "\n";
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

}