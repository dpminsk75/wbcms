<?php

namespace app\components;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;

class WbApiFinance extends Component
{
    private $baseUrl = 'https://finance-api.wildberries.ru';

    /**
     * Токен компании (companies.api_key), передаётся явно при создании компонента:
     * new WbApiFinance(['token' => $token])
     * Больше не читается из Yii::$app->params — это убирает побочный эффект,
     * когда токен одной компании мог "утечь" в вызов для другой, если где-то
     * забыли переустановить глобальный параметр перед созданием сервиса.
     *
     * @var string|null
     */
    public $token;

    public function init()
    {
        parent::init();

        if (empty($this->token)) {
            throw new InvalidConfigException('WbApiFinance: не передан токен компании (свойство "token").');
        }
    }

    /**
     * Получение детализации отчетов по периоду (Новый POST-метод)
     * @param string $dateFrom
     * @param string $dateTo
     * @param int $rrdid Идентификатор строки для пагинации
     * @param string $period Период отчетов ('daily' или 'weekly')
     * @return array ['status' => int, 'data' => array|null]
     */
    public function getDetailByPeriod($dateFrom, $dateTo, $rrdid = 0, $period = 'daily')
    {
        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($this->baseUrl . '/api/finance/v1/sales-reports/detailed')
            ->setFormat(Client::FORMAT_JSON)
            ->setOptions([
                'timeout' => 60, // страховка от зависания запроса на медленной сети
            ])
            ->setData([
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'limit'    => 100000, // Максимальный лимит по спецификации
                'period'   => $period, // Параметр успешно сохранен
                'rrdid'    => (int)$rrdid
            ])
            ->addHeaders(['Authorization' => $this->token])
            ->send();

        return [
            'status' => $response->statusCode,
            'data'   => $response->data
        ];
    }
}
