<?php

namespace app\components;

use yii\base\Component;
use yii\httpclient\Client;
use Yii;

class WbApiStats extends Component
{
    private $baseUrl = 'https://statistics-api.wildberries.ru';

    /**
     * @param string $dateFrom
     * @param string $dateTo
     * @param int $rrdid Идентификатор строки, с которой начать выгрузку
     * @return array ['status' => int, 'data' => array|null]
     */
/*    
    public function getDetailByPeriod($dateFrom, $dateTo, $rrdid = 0)
    {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->baseUrl . '/api/v5/supplier/reportDetailByPeriod')
            ->setData([
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'limit' => '5000',
                'period' => 'daily',
                'rrdid' => $rrdid
            ])
            ->addHeaders(['Authorization' => $token])
            ->send();

        return [
            'status' => $response->statusCode,
            'data' => $response->data
        ];
    }
*/
// WbApiStats.php
    public function getDetailByPeriod($dateFrom, $dateTo, $rrdid = 0)
    {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($this->baseUrl . '/api/v5/supplier/reportDetailByPeriod')
            ->setData([
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'limit' => 100000, // Увеличили до максимума
                'period' => 'daily',
                'rrdid' => $rrdid
            ])
            ->addHeaders(['Authorization' => $token])
            ->send();

        return [
            'status' => $response->statusCode,
            'data' => $response->data
        ];
    }
}