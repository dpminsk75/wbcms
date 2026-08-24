<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use yii\httpclient\Response;

/**
 * Единый HTTP-клиент для WB API с учётом мультикомпанейности.
 *
 * Токен и company_id передаются КАЖДЫМ вызовом (а не хранятся в компоненте),
 * чтобы один экземпляр безопасно обслуживал цикл по компаниям:
 *
 *   foreach (CompanyManager::getActiveCompanies() as $c) {
 *       $data = Yii::$app->wbHttpClient->get($url, $params, $c['api_key'], $c['id']);
 *   }
 *
 * - 429 → уважает Retry-After, иначе 60с, до 3 повторов
 * - 5xx / сетевые ошибки → экспоненциальный backoff
 * - Логирует с company_id для диагностики лимитов по кабинетам
 */
class WbHttpClient extends Component
{
    public int $timeout = 30;
    public int $maxRetries = 3;
    public int $default429Wait = 60;

    private ?Client $client = null;

    public function init(): void
    {
        parent::init();
        $this->client = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
            'requestConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
            'responseConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
        ]);
    }

    /**
     * GET запрос.
     *
     * @param string $url полный URL или путь (https://...)
     * @param array $params query-параметры
     * @param string $token WB JWT токен конкретной компании
     * @param int|null $companyId для логов (обязателен в циклах по компаниям)
     */
    public function get(string $url, array $params = [], string $token = '', ?int $companyId = null, ?int $retries = null): Response
    {
        return $this->request('GET', $url, $params, $token, $companyId, $retries);
    }

    /**
     * POST запрос с JSON телом.
     *
     * @param string $url
     * @param array $payload тело запроса (будет json_encode)
     * @param string $token
     * @param int|null $companyId
     */
    public function post(string $url, array $payload = [], string $token = '', ?int $companyId = null, ?int $retries = null): Response
    {
        return $this->request('POST', $url, $payload, $token, $companyId, $retries, true);
    }

    /**
     * Базовый запрос с ретраями.
     *
     * @param string $method GET|POST
     * @param string $url
     * @param array $data query для GET, body для POST
     * @param string $token
     * @param int|null $companyId
     * @param int|null $retries
     * @param bool $isJsonBody
     */
    public function request(string $method, string $url, array $data = [], string $token = '', ?int $companyId = null, ?int $retries = null, bool $isJsonBody = false): Response
    {
        $retries = $retries ?? $this->maxRetries;
        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = $token;
        }

        $lastResponse = null;
        $lastException = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $request = $this->client->createRequest()
                    ->setMethod($method)
                    ->setUrl($url)
                    ->setHeaders($headers)
                    ->setOptions(['timeout' => $this->timeout]);

                if (strtoupper($method) === 'GET') {
                    if (!empty($data)) {
                        $request->setData($data);
                        $request->setFormat(Client::FORMAT_URLENCODED);
                    }
                } else {
                    if ($isJsonBody) {
                        $request->setData($data);
                        $request->setFormat(Client::FORMAT_JSON);
                    } else {
                        $request->setData($data);
                    }
                }

                /** @var Response $response */
                $response = $request->send();
                $lastResponse = $response;
                $status = $response->getStatusCode();

                if ($status === 429) {
                    $wait = $this->getRetryAfter($response, $this->default429Wait);
                    $this->logWarning("WB API 429", $companyId, $url, $attempt, $wait, $status);
                    if ($attempt < $retries) {
                        sleep($wait);
                        continue;
                    }
                    return $response;
                }

                if ($status >= 500 && $status < 600) {
                    $wait = (int)pow(2, $attempt);
                    $this->logWarning("WB API 5xx", $companyId, $url, $attempt, $wait, $status);
                    if ($attempt < $retries) {
                        sleep($wait);
                        continue;
                    }
                    return $response;
                }

                return $response;

            } catch (\Throwable $e) {
                $lastException = $e;
                $wait = (int)pow(2, $attempt);
                $this->logWarning("WB API network error: " . $e->getMessage(), $companyId, $url, $attempt, $wait, 0);
                if ($attempt < $retries) {
                    sleep($wait);
                    continue;
                }
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return $lastResponse;
    }

    private function getRetryAfter(Response $response, int $default): int
    {
        $headers = $response->getHeaders();
        $retryAfter = $headers->get('Retry-After') ?? $headers->get('retry-after');
        if ($retryAfter !== null) {
            $retryAfter = is_array($retryAfter) ? reset($retryAfter) : $retryAfter;
            if (is_numeric($retryAfter)) {
                return max(1, (int)$retryAfter);
            }
            $ts = strtotime((string)$retryAfter);
            if ($ts !== false) {
                return max(1, $ts - time());
            }
        }
        return $default;
    }

    private function logWarning(string $message, ?int $companyId, string $url, int $attempt, int $wait, int $status): void
    {
        $prefix = $companyId !== null ? "[company:{$companyId}] " : "";
        $statusStr = $status ? " status={$status}" : "";
        Yii::warning("{$prefix}{$message} url={$url} attempt={$attempt} wait={$wait}s{$statusStr}", 'wb_api');
    }
}
