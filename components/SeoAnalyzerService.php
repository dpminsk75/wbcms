<?php
namespace app\components;

use Yii;
use app\models\WbCard;

/**
 * Сбор сигналов для nmID и генерация рекомендаций через OpenRouter с ротацией моделей из wb_seo_model.
 */
class SeoAnalyzerService
{
    public ?string $lastError = null;

    public function buildPrompt(int $nmID, int $companyId, string $dateFrom, string $dateTo): ?array
    {
        $card = WbCard::find()->where(['nmID' => $nmID])->one();
        if (!$card) {
            return null;
        }
        $charsRaw = is_string($card->characteristics) ? json_decode($card->characteristics, true) : $card->characteristics;
        $chars = [];
        if (is_array($charsRaw)) {
            foreach (array_slice($charsRaw, 0, 7) as $c) {
                $val = $c['value'] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $chars[] = ['name' => $c['name'] ?? '', 'value' => $val];
            }
        }
        $phraseRows = \app\models\WbSrReportItemPhrases::find()
            ->select([
                'phrase',
                'avg(week_frequency) as avg_freq',
                'avg(avg_position) as avg_pos',
                'sum(clicks) as total_clicks',
                'sum(orders) as total_orders',
                'avg(ctr) as avg_ctr',
            ])
            ->where(['nmID' => $nmID])
            ->andWhere(['between', 'date', $dateFrom, $dateTo])
            ->groupBy('phrase')
            ->orderBy(['total_clicks' => SORT_DESC])
            ->limit(30)
            ->asArray()
            ->all();

        $topClicks = array_slice($phraseRows, 0, 10);
        $withOrders = array_values(array_filter($phraseRows, fn($r) => (int)$r['total_orders'] > 0));
        usort($withOrders, fn($a,$b) => $b['total_orders'] <=> $a['total_orders']);
        $withOrders = array_slice($withOrders, 0, 5);
        $opportunity = array_values(array_filter($phraseRows, fn($r) => (int)$r['avg_pos'] >= 11 && (int)$r['avg_pos'] <= 50 && (int)$r['avg_freq'] > 500));
        usort($opportunity, fn($a,$b) => $b['avg_freq'] <=> $a['avg_freq']);
        $opportunity = array_slice($opportunity, 0, 5);

        $signals = ['top_clicks'=>$topClicks,'with_orders'=>$withOrders,'opportunity'=>$opportunity];
        // целевые фразы (ручные, приоритет)
        $targetPhrases = \app\models\WbSeoTarget::find()->select(['phrase','priority'])
            ->where(['nmID'=>$nmID,'is_active'=>1])->orderBy(['priority'=>SORT_ASC,'id'=>SORT_ASC])->limit(10)->asArray()->all();
        $signals['targets'] = $targetPhrases;
        $company = \app\models\Company::findOne($companyId);
        $descMin = $company && $company->seo_desc_min !== null ? (int)$company->seo_desc_min : (int)(Yii::$app->params['seoDescriptionMin'] ?? 800);
        $descMax = $company && $company->seo_desc_max !== null ? (int)$company->seo_desc_max : (int)(Yii::$app->params['seoDescriptionMax'] ?? 1200);
        if ($descMin < 300) $descMin = 300;
        if ($descMax < $descMin) $descMax = $descMin + 400;
        $defaultSystem = 'Ты — SEO-специалист Wildberries. '
            . 'На основе текущего title/description карточки и данных по поисковым фразам (частотность, средняя позиция, клики, заказы) '
            . 'предложи улучшенный заголовок и описание. '
            . "Требования: заголовок 85-120 символов, ключевые фразы в начале, читаемость для человека важнее спама, сохрани бренд если он есть. "
            . "Описание {$descMin}-{$descMax} знаков: первые 200 знаков — главный ключ + выгода, затем 3-5 буллетов с характеристиками/применением, вставь ключи естественно. Обязательно уложись в {$descMin}-{$descMax} знаков, не короче {$descMin}. "
            . 'Не выдумывай характеристики, не добавляй ключи которых нет в списке фраз. '
            . 'Ответ СТРОГО JSON без markdown: {"new_title":"...","new_description":"...","keywords_added":["..."],"keywords_removed":["..."],"rationale":"кратко почему","confidence":0.0-1.0,"risks":"..."}';
        $isCustom = $company && $company->seo_prompt && trim($company->seo_prompt) !== '';
        $baseSystem = $isCustom ? str_replace(['{DESC_MIN}','{DESC_MAX}'], [$descMin,$descMax], $company->seo_prompt) : $defaultSystem;
        // добавляем целевые фразы в system если есть
        $system = $baseSystem;
        if (!empty($targetPhrases)) {
            $tList = implode(', ', array_map(fn($r)=>'"'.$r['phrase'].'"', $targetPhrases));
            $targetLine = " Обязательно включи целевые фразы: [$tList] — приоритет выше статистики, вставь естественно.";
            if (!str_contains($system, 'целев')) $system .= $targetLine;
        }
        $promptSource = $isCustom ? "company:{$company->id} ({$company->name})" : "default (params.php)";
        if (!empty($targetPhrases)) $promptSource .= " +targets:" . count($targetPhrases);

        $userData = [
            'nmID' => $nmID,
            'company_id' => $companyId,
            'subject' => $card->subjectName,
            'brand' => $card->brand,
            'current_title' => $card->title,
            'current_description' => $card->description,
            'characteristics' => $chars,
            'top_phrases_by_clicks' => array_map(fn($r) => ['phrase'=>$r['phrase'],'freq'=>(int)$r['avg_freq'],'avg_pos'=>round((float)$r['avg_pos'],1),'clicks'=>(int)$r['total_clicks'],'orders'=>(int)$r['total_orders']], $topClicks),
            'phrases_with_orders' => array_map(fn($r) => ['phrase'=>$r['phrase'],'freq'=>(int)$r['avg_freq'],'avg_pos'=>round((float)$r['avg_pos'],1),'clicks'=>(int)$r['total_clicks'],'orders'=>(int)$r['total_orders']], $withOrders),
            'opportunity_phrases_pos_11_50' => array_map(fn($r) => ['phrase'=>$r['phrase'],'freq'=>(int)$r['avg_freq'],'avg_pos'=>round((float)$r['avg_pos'],1),'clicks'=>(int)$r['total_clicks']], $opportunity),
            'target_phrases' => array_map(fn($r)=>$r['phrase'], $targetPhrases),
            'period' => "$dateFrom — $dateTo",
        ];
        $messages = [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>json_encode($userData, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)],
        ];
        return ['card'=>$card,'signals'=>$signals,'messages'=>$messages,'userData'=>$userData,'prompt_source'=>$promptSource,'descMin'=>$descMin,'descMax'=>$descMax];
    }

    public function generate(int $nmID, int $companyId, string $dateFrom, string $dateTo, ?string $model = null): ?array
    {
        $this->lastError = null;
        $built = $this->buildPrompt($nmID, $companyId, $dateFrom, $dateTo);
        if (!$built) {
            $this->lastError = "buildPrompt failed: карточка $nmID не найдена";
            return null;
        }
        /** @var OpenRouterClient $or */
        $or = Yii::createObject(OpenRouterClient::class);

        $fallbackHardcoded = ['z-ai/glm-5.2:free','minimax/minimax-m3:free','nvidia/nemotron-3.5-lightning:free','inclusionai/ling-3.0-flash-fin:free','google/gemma-4-26b-a4b-it:free'];
        // прокидываем companyId для per-company key/referer
        $companyIdForClient = $companyId;
        $candidates = [];
        if ($model) {
            // если переданная модель в кулдауне — пропускаем её
            $skipFirst = false;
            try {
                $firstRow = \app\models\WbSeoModel::findOne(['model_id'=>$model]);
                if ($firstRow && $firstRow->cooldown_until && strtotime($firstRow->cooldown_until) > time()) {
                    $skipFirst = true;
                }
            } catch (\Throwable $ignored) {}
            if (!$skipFirst) $candidates = [$model];
            try {
                $rows = \app\models\WbSeoModel::getActiveOrdered();
                foreach ($rows as $r) {
                    if ($r->model_id !== $model) $candidates[] = $r->model_id;
                }
                if (count($candidates) === 0) {
                    // все в кулдауне — берем хоть что-то из фолбэка
                    foreach ($fallbackHardcoded as $fb) if ($fb !== $model) $candidates[] = $fb;
                } elseif (count($candidates) === 1 && $candidates[0]===$model) {
                    foreach ($fallbackHardcoded as $fb) if ($fb !== $model) $candidates[] = $fb;
                }
            } catch (\Throwable $ignored) {
                if (!$skipFirst) {
                    foreach ($fallbackHardcoded as $fb) if ($fb !== $model) $candidates[] = $fb;
                } else {
                    $candidates = $fallbackHardcoded;
                }
            }
        } else {
            try {
                $rows = \app\models\WbSeoModel::getActiveOrdered();
            } catch (\Throwable $e) {
                $rows = [];
            }
            if (empty($rows)) {
                $p = Yii::$app->params['openRouterModel'] ?? 'google/gemma-4-31b-it:free';
                $candidates = [$p];
                foreach ($fallbackHardcoded as $fb) if ($fb !== $p) $candidates[] = $fb;
            } else {
                $candidates = array_map(fn($r)=>$r->model_id, $rows);
            }
        }

        $lastErr = null;
        foreach ($candidates as $idx => $tryModel) {
            if (PHP_SAPI === 'cli' && defined('STDOUT')) {
                echo "  → пробую модель " . ($idx+1) . "/" . count($candidates) . ": $tryModel ...\n";
                if (function_exists('flush')) { @flush(); }
            }
            Yii::info("SEO try model $tryModel for nmID $nmID", 'seo');
            $res = $or->chat($built['messages'], $tryModel, null, 0.4, $companyIdForClient);
            if ($res) {
                $content = trim($res['content']);
                if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) $content = trim($m[1]);
                $parsed = json_decode($content, true);
                if (!is_array($parsed) || empty($parsed['new_title']) || empty($parsed['new_description'])) {
                    $err = "JSON parse failed model=$tryModel: " . substr($content,0,800);
                    $this->lastError = $err;
                    Yii::warning($err, 'seo');
                    try { \app\models\WbSeoModel::findOne(['model_id'=>$tryModel])?->markError($err, false); } catch (\Throwable $ignored) {}
                    $lastErr = $err;
                    continue;
                }
                $parsed['new_title'] = trim((string)$parsed['new_title']);
                $parsed['new_description'] = trim((string)$parsed['new_description']);
                try { \app\models\WbSeoModel::findOne(['model_id'=>$tryModel])?->markSuccess(); } catch (\Throwable $ignored) {}
                if ($tryModel !== $candidates[0]) Yii::info("SEO fallback success nmID=$nmID model=$tryModel", 'seo');
                $parsed['prompt_tokens'] = $res['prompt_tokens'];
                $parsed['completion_tokens'] = $res['completion_tokens'];
                $parsed['raw'] = $res['raw'];
                $parsed['raw_content'] = $res['content'];
                $parsed['_userData'] = $built['userData'];
                $parsed['_card'] = $built['card'];
                $parsed['_used_model'] = $tryModel;
                return $parsed;
            } else {
                $err = $or->lastError ?: 'null';
                $is429 = str_contains($err, '429') || str_contains($err, 'rate-limited');
                try { \app\models\WbSeoModel::findOne(['model_id'=>$tryModel])?->markError($err, $is429); } catch (\Throwable $ignored) {}
                $lastErr = "model $tryModel: $err";
                continue;
            }
        }
        $this->lastError = $lastErr ?: 'All models failed';
        return null;
    }
}
