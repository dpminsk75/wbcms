<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use yii\httpclient\Response;

/**
 * Клиент OpenRouter для бесплатный моделей.
 * Использует Yii httpclient, ретраи 429/5xx, X-RateLimit-Retry.
 *
 * Конфиг в params.php:
 *  openRouterApiKey, openRouterModel, openRouterDailyLimit
 */
class OpenRouterClient extends Component
{
    public string $baseUrl = 'https://openrouter.ai/api/v1';
    public int $timeout = 60;
    public int $maxRetries = 3;
    public int $default429Wait = 20;

    public ?string $lastError = null;
    public ?int $lastStatus = null;
    public ?string $lastResponse = null;

    private ?Client $client = null;

    public function init(): void
    {
        parent::init();
        $this->client = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
            'requestConfig' => ['format' => Client::FORMAT_JSON],
            'responseConfig' => ['format' => Client::FORMAT_JSON],
        ]);
    }

    /**
     * Chat completion.
     * @param array $messages [['role'=>'system','content'=>...], ...]
     * @param string $model например google/gemma-3-27b-it:free
     * @return array|null ['content'=>string, 'prompt_tokens'=>int, 'completion_tokens'=>int, 'raw'=>array] или null при ошибке
     */
    public function chat(array $messages, ?string $model = null, ?string $apiKey = null, float $temperature = 0.4, ?int $companyId = null): ?array
    {
        // per-company overrides
        $company = $companyId ? \app\models\Company::findOne($companyId) : null;
        $model = $model ?: ($company && $company->seo_model ? $company->seo_model : (Yii::$app->params['openRouterModel'] ?? 'minimax/minimax-m3:free'));
        $this->lastError = null;
        $this->lastStatus = null;
        $this->lastResponse = null;
        $apiKey = $apiKey ?: ($company && $company->seo_openrouter_key ? $company->seo_openrouter_key : (Yii::$app->params['openRouterApiKey'] ?? ''));
        if ($apiKey === '') {
            $this->lastError = 'openRouterApiKey is empty in params.php / companies.seo_openrouter_key';
            Yii::error($this->lastError, 'seo');
            return null;
        }

        $url = $this->baseUrl . '/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => 1500,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $referer = ($company && $company->seo_openrouter_referer) ?: (Yii::$app->params['openRouterReferer'] ?? 'https://wbcms.local');
        $title = ($company && $company->seo_openrouter_title) ?: (Yii::$app->params['openRouterTitle'] ?? 'wbcms SEO');
        if ($referer) {
            $headers['HTTP-Referer'] = $referer;
            $headers['X-Title'] = $title;
        }

        $retries = $this->maxRetries;
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if (PHP_SAPI === 'cli' && defined('STDOUT')) {
                echo "    POST $model" . ($retries > 0 ? " attempt " . ($attempt+1) . "/" . ($retries+1) : "") . " ...\n"; @flush();
            }
            try {
                $request = $this->client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($url)
                    ->setHeaders($headers)
                    ->setFormat(Client::FORMAT_JSON)
                    ->setData($payload)
                    ->setOptions(['timeout' => $this->timeout]);

                /** @var Response $response */
                $response = $request->send();
                $status = $response->getStatusCode();
                if (PHP_SAPI === 'cli' && defined('STDOUT')) {
                    echo "    <- HTTP $status\n"; @flush();
                }

                $this->lastStatus = $status;
                $this->lastResponse = $response->content;
                if ($status === 429) {
                    $this->lastError = "HTTP 429 rate limited model=$model body=" . substr($response->content,0,500);
                    $wait = $this->getRetryAfter($response, $this->default429Wait);
                    Yii::warning("OpenRouter 429 attempt=$attempt wait=$wait model=$model body=" . substr($response->content,0,300), 'seo');
                    if (PHP_SAPI === 'cli' && defined('STDOUT')) {
                        echo "    -> 429, retry $wait s (attempt " . ($attempt+1) . "/" . ($retries+1) . ") ...\n"; @flush();
                    }
                    if ($attempt < $retries) { sleep($wait); continue; }
                    return null;
                }
                if ($status >= 500 && $status < 600) {
                    $this->lastError = "HTTP $status 5xx body=" . substr($response->content,0,500);
                    $wait = (int)pow(2, $attempt);
                    Yii::warning("OpenRouter 5xx $status attempt=$attempt wait=$wait", 'seo');
                    if ($attempt < $retries) {
                        if (PHP_SAPI === 'cli' && defined('STDOUT')) { echo "    5xx, retry $wait s (attempt " . ($attempt+1) . "/" . ($retries+1) . ") ...\n"; @flush(); }
                        sleep($wait); continue;
                    }
                    return null;
                }
                if (!$response->isOk) {
                    $this->lastError = "HTTP $status error: " . substr($response->content,0,800);
                    Yii::error($this->lastError, 'seo');
                    return null;
                }

                $data = $response->data;
                if ($data === null) {
                    $data = json_decode($response->content, true);
                }
                $content = $data['choices'][0]['message']['content'] ?? null;
                if ($content === null) {
                    $this->lastError = 'Empty choices: ' . substr($response->content,0,800);
                    Yii::error($this->lastError, 'seo');
                    return null;
                }
                return [
                    'content' => $content,
                    'prompt_tokens' => $data['usage']['prompt_tokens'] ?? null,
                    'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
                    'raw' => $data,
                ];
            } catch (\Throwable $e) {
                $this->lastError = 'Network exception: ' . $e->getMessage();
                if (PHP_SAPI === 'cli' && defined('STDOUT')) {
                    echo "    !! exception: " . $e->getMessage() . "\n"; @flush();
                }
                $wait = (int)pow(2, $attempt);
                Yii::warning("OpenRouter network error: {$e->getMessage()} attempt=$attempt wait=$wait", 'seo');
                if ($attempt < $retries) {
                    if (PHP_SAPI === 'cli' && defined('STDOUT')) { echo "    sleep $wait s ...\n"; @flush(); }
                    sleep($wait); continue;
                }
                // не бросаем, возвращаем null чтобы контроллер показал ошибку
                return null;
            }
        }
        return null;
    }

    /**
     * Получить список моделей, отфильтровать free.
     * GET https://openrouter.ai/api/v1/models — без авторизации, но с ключом отдает лимиты.
     * @return array|null ['all'=>[...], 'free'=>[...]] или null при ошибке
     */
    public function getModels(?string $apiKey = null): ?array
    {
        $this->lastError = null;
        $url = $this->baseUrl . '/models';
        $headers = ['Accept' => 'application/json'];
        $apiKey = $apiKey ?: (Yii::$app->params['openRouterApiKey'] ?? '');
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        try {
            $request = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setHeaders($headers)
                ->setOptions(['timeout' => 15]);
            $response = $request->send();
            if (!$response->isOk) {
                $this->lastError = "models HTTP {$response->statusCode}: " . substr($response->content,0,500);
                Yii::error($this->lastError, 'seo');
                return null;
            }
            $data = $response->data;
            if ($data === null) $data = json_decode($response->content, true);
            $all = $data['data'] ?? [];
            $free = array_values(array_filter($all, function($m) {
                $id = $m['id'] ?? '';
                // free-модели помечены :free в id или pricing = 0
                if (str_ends_with($id, ':free')) return true;
                $pricing = $m['pricing'] ?? [];
                $prompt = $pricing['prompt'] ?? null;
                return $prompt === '0' || $prompt === 0 || $prompt === '0.000000';
            }));
            return ['all' => $all, 'free' => $free, 'raw' => $data];
        } catch (\Throwable $e) {
            $this->lastError = 'models exception: ' . $e->getMessage();
            Yii::error($this->lastError, 'seo');
            return null;
        }
    }

    private function getRetryAfter(Response $response, int $default): int
    {
        $headers = $response->getHeaders();
        $candidates = [
            $headers->get('Retry-After'),
            $headers->get('retry-after'),
            $headers->get('X-RateLimit-Retry'),
            $headers->get('x-ratelimit-retry'),
        ];
        foreach ($candidates as $val) {
            if ($val === null) continue;
            $val = is_array($val) ? reset($val) : $val;
            if (is_numeric($val)) return max(1, (int)$val);
            $ts = strtotime((string)$val);
            if ($ts !== false) return max(1, $ts - time());
        }
        try {
            $data = $response->data ?? json_decode($response->content, true);
            if (is_array($data)) {
                $detail = $data['error']['message'] ?? $data['detail'] ?? '';
                if (is_string($detail) && preg_match('/retry.*?(\d+)\s*s/i', $detail, $m)) {
                    return max(1, (int)$m[1]);
                }
            }
        } catch (\Throwable $ignored) {}
        return $default;
    }
}
