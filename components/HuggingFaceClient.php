<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use yii\httpclient\Response;

/**
 * Клиент Hugging Face Inference API.
 * Бесплатный tier: https://huggingface.co/settings/tokens
 * Дока: https://huggingface.co/docs/api-inference
 */
class HuggingFaceClient extends Component
{
    public string $baseUrl = 'https://router.huggingface.co/hf-inference';
    public int $timeout = 30;
    public int $maxRetries = 1;

    public ?string $lastError = null;
    public ?int $lastStatus = null;
    public $lastResponse = null;

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
     * @param array $messages [['role'=>'system','content'=>...],['role'=>'user','content'=>...]]
     * @return array|null ['content'=>string,'prompt_tokens'=>null,'completion_tokens'=>null,'raw'=>mixed]
     */
    public function chat(array $messages, ?string $model = null, ?string $apiKey = null, float $temperature = 0.4): ?array
    {
        $this->lastError = null;
        $this->lastStatus = null;
        $this->lastResponse = null;

        $model = $model ?: (Yii::$app->params['huggingFaceModel'] ?? 'mistralai/Mistral-7B-Instruct-v0.3');
        $apiKey = $apiKey ?: (Yii::$app->params['huggingFaceApiKey'] ?? '');

        if ($apiKey === '') {
            $this->lastError = 'huggingFaceApiKey is empty in params.php';
            Yii::error($this->lastError, 'hf');
            return null;
        }
        if (!$model) {
            $this->lastError = 'model is empty';
            return null;
        }

        // HF не знает system/user — склеиваем в один prompt
        $prompt = $this->messagesToPrompt($messages);

        $url = $this->baseUrl . '/models/' . $model;
        $payload = [
            'inputs' => $prompt,
            'parameters' => [
                'temperature' => $temperature,
                'max_new_tokens' => 800,
                'return_full_text' => false,
            ],
            'options' => [
                'wait_for_model' => true,
                'use_cache' => false,
            ],
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'wbcms/1.0 (+https://wbcms.local)',
        ];

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
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
                $this->lastStatus = $response->getStatusCode();
                $this->lastResponse = $response->content;

                if ($response->getStatusCode() === 503) {
                    $this->lastError = "HF 503 loading: " . substr($response->content, 0, 400);
                    Yii::warning($this->lastError, 'hf');
                    if ($attempt < $this->maxRetries) { sleep(5); continue; }
                    return null;
                }
                if (!$response->isOk) {
                    $this->lastError = "HTTP {$response->getStatusCode()}: " . substr($response->content, 0, 600);
                    Yii::error($this->lastError, 'hf');
                    return null;
                }

                $data = $response->data;
                if ($data === null) $data = json_decode($response->content, true);

                // HF возвращает [{"generated_text":"..."}] или {"generated_text":"..."}
                $content = null;
                if (isset($data[0]['generated_text'])) $content = $data[0]['generated_text'];
                elseif (isset($data['generated_text'])) $content = $data['generated_text'];
                elseif (isset($data[0]['content'])) $content = $data[0]['content'];

                if ($content === null || trim($content) === '') {
                    $this->lastError = 'Empty generated_text: ' . substr($response->content, 0, 600);
                    Yii::warning($this->lastError, 'hf');
                    return null;
                }

                return [
                    'content' => trim($content),
                    'prompt_tokens' => null,
                    'completion_tokens' => null,
                    'raw' => $data,
                ];
            } catch (\Throwable $e) {
                $this->lastError = 'HF exception: ' . $e->getMessage();
                Yii::warning($this->lastError, 'hf');
                if ($attempt < $this->maxRetries) { sleep(2); continue; }
                return null;
            }
        }
        return null;
    }

    /**
     * Список популярных моделей с HF (фильтр instruct/chat).
     * GET https://huggingface.co/api/models?limit=30&filter=text-generation&sort=downloads&direction=-1
     */
    public function getModels(int $limit = 20): ?array
    {
        $this->lastError = null;
        $url = 'https://huggingface.co/api/models';
        try {
            $apiKey = Yii::$app->params['huggingFaceApiKey'] ?? '';
            $headers = ['User-Agent' => 'wbcms/1.0 (+https://wbcms.local)', 'Accept' => 'application/json'];
            if ($apiKey !== '') $headers['Authorization'] = 'Bearer ' . $apiKey;
            $request = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setFormat(Client::FORMAT_URLENCODED)
                ->setHeaders($headers)
                ->setData(['limit' => $limit, 'pipeline_tag' => 'text-generation', 'sort' => 'downloads', 'direction' => -1, 'full' => 'false', 'config' => 'false', 'siblings' => 'false', 'tags' => 'false'])
                ->setOptions(['timeout' => 10]);
            $response = $request->send();
            if (!$response->isOk) {
                $this->lastError = "HF models HTTP {$response->getStatusCode()}: " . substr($response->content, 0, 400);
                return null;
            }
            $data = $response->data;
            if ($data === null) $data = json_decode($response->content, true);
            return $data;
        } catch (\Throwable $e) {
            $this->lastError = 'HF models exception: ' . $e->getMessage();
            return null;
        }
    }

    private function messagesToPrompt(array $messages): string
    {
        $parts = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            if ($role === 'system') $parts[] = "[SYSTEM] " . $content;
            else $parts[] = $content;
        }
        return implode("\n\n", $parts) . "\n\n[ASSISTANT]";
    }
}
