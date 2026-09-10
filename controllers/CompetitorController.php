<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use app\models\WbCompetitorCard;
use app\models\WbCompetitorDetail;
use app\models\WbCompetitorAnalysis;
use app\models\WbCompetitorSummary;
use app\models\WbCard;

class CompetitorController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => fn() => Yii::$app->user->can('viewSeo') || Yii::$app->user->can('admin'),
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'remove' => ['POST'],
                    'analyze' => ['POST'],
                    'analyze-direct' => ['POST'],
                    'analyze-all' => ['POST'],
                    'summary' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Список товаров у которых есть конкуренты
     */
    public function actionIndex()
    {
        $companyId = Yii::$app->user->identity->company_id ?? null;

        $query = WbCompetitorCard::find()
            ->select([
                'source_nm_id',
                'cnt' => new \yii\db\Expression('COUNT(DISTINCT nm_id)'),
                'phrases' => new \yii\db\Expression('COUNT(DISTINCT query_phrase)'),
            ])
            ->groupBy('source_nm_id')
            ->orderBy(['source_nm_id' => SORT_ASC]);

        $rows = $query->asArray()->all();

        // обогащаем названиями карточек
        $nmIds = array_column($rows, 'source_nm_id');
        $cards = WbCard::find()->where(['nmID' => $nmIds])->indexBy('nmID')->asArray()->all();

        return $this->render('index', [
            'rows' => $rows,
            'cards' => $cards,
        ]);
    }

    /**
     * Выбор фраз для анализа по nmID
     */
    public function actionSelect($nm_id)
    {
        $nm_id = (int)$nm_id;

        // Уже отобранные ранее фразы (если есть состояние)
        $selectedPhrases = WbCompetitorAnalysis::find()
            ->select('query_phrase')
            ->where(['source_nm_id' => $nm_id])
            ->groupBy('query_phrase')
            ->column();

        $allPhrases = WbCompetitorCard::getUniquePhrases($nm_id);

        // Получаем карточку нашего товара
        $card = WbCard::find()->where(['nmID' => $nm_id])->asArray()->one();

        return $this->render('select', [
            'nmId' => $nm_id,
            'phrases' => $allPhrases,
            'selectedPhrases' => $selectedPhrases,
            'card' => $card,
        ]);
    }

    /**
     * Сохранение выбранных фраз и показ конкурентов
     */
    public function actionSelected($nm_id)
    {
        $nm_id = (int)$nm_id;

        if (Yii::$app->request->isPost) {
            $phrases = Yii::$app->request->post('phrases', []);
            $positionMax = (int)Yii::$app->request->post('position_max', 0);

            if (empty($phrases)) {
                return $this->redirect(['select', 'nm_id' => $nm_id]);
            }

            $this->saveCompetitors($nm_id, $phrases, $positionMax);

            return $this->redirect(['selected', 'nm_id' => $nm_id]);
        }

        // GET — загружаем из БД (без пересохранения)
        $companyId = Yii::$app->user->identity->company_id ?? null;

        // Все конкуренты для этого товара (selected + analyzed) — исключаем наш
        $allAnalysis = WbCompetitorAnalysis::find()
            ->where(['source_nm_id' => $nm_id])
            ->andWhere(['<>', 'competitor_nm_id', $nm_id])
            ->orderBy(['competitor_nm_id' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        if (empty($allAnalysis)) {
            return $this->redirect(['select', 'nm_id' => $nm_id]);
        }

        // Уникальные конкуренты
        $compNmIds = array_unique(array_column($allAnalysis, 'competitor_nm_id'));

        // Группируем запросы по конкуренту
        $grouped = [];
        foreach ($allAnalysis as $ar) {
            $nid = $ar['competitor_nm_id'];
            if (!isset($grouped[$nid])) {
                $grouped[$nid] = [
                    'nm_id' => $nid,
                    'queries' => [],
                ];
            }
            $grouped[$nid]['queries'][] = [
                'phrase' => $ar['query_phrase'],
                'position' => $ar['position'],
            ];
        }
        $competitors = array_values($grouped);

        // Детали конкурентов
        $details = WbCompetitorDetail::find()
            ->where(['nm_id' => $compNmIds])
            ->indexBy('nm_id')
            ->asArray()
            ->all();

        // Analysis-записи (для чекбоксов)
        $analysisMap = [];
        foreach ($allAnalysis as $ar) {
            if (!isset($analysisMap[$ar['competitor_nm_id']])) {
                $analysisMap[$ar['competitor_nm_id']] = $ar;
            }
        }

        // Определяем позицию из данных (максимальная позиция среди всех)
        $positions = array_column($allAnalysis, 'position');
        $positionMax = $positions ? max($positions) : 0;

        $card = WbCard::find()->where(['nmID' => $nm_id])->asArray()->one();

        return $this->render('selected', [
            'nmId' => $nm_id,
            'competitors' => $competitors,
            'details' => $details,
            'analysisMap' => $analysisMap,
            'card' => $card,
            'positionMax' => $positionMax,
        ]);
    }

    /**
     * Сохранение конкурентов (вызывается только из POST)
     */
    private function saveCompetitors($nmId, array $phrases, int $positionMax): void
    {
        // company — из CompanyManager (как в SeoController), fallback на карточку
        $cardCompanyId = \app\models\WbCard::find()->select('company_id')->where(['nmID' => $nmId])->scalar();
        $companyId = $this->resolveCompanyId($cardCompanyId);
        $tx = Yii::$app->db->beginTransaction();
        try {
            // Удаляем старые "selected" (не трогаем analyzed)
            WbCompetitorAnalysis::deleteAll([
                'and',
                ['source_nm_id' => $nmId],
                ['status' => 'selected'],
            ]);

            // Получаем уникальных конкурентов с фильтром по позиции
            $competitors = WbCompetitorCard::getUniqueCompetitors($nmId, $phrases, $positionMax > 0 ? $positionMax : null);

            // Загружаем все существующие записи одним запросом
            $existingRows = WbCompetitorAnalysis::find()
                ->where(['source_nm_id' => $nmId])
                ->asArray()
                ->all();
            $existingMap = [];
            foreach ($existingRows as $er) {
                $key = $er['competitor_nm_id'] . '|' . $er['query_phrase'];
                $existingMap[$key] = $er;
            }

            $toInsert = [];
            // Сохраняем как selected (обновляем существующие, вставляем новые) — исключаем наш товар
            foreach ($competitors as $comp) {
                if ($comp['nm_id'] == $nmId) continue;
                foreach ($comp['queries'] as $q) {
                    $key = $comp['nm_id'] . '|' . $q['phrase'];
                    if (isset($existingMap[$key])) {
                        WbCompetitorAnalysis::updateAll(
                            ['status' => 'selected', 'position' => $q['position']],
                            ['id' => $existingMap[$key]['id']]
                        );
                    } else {
                        $toInsert[] = [$companyId, $nmId, $comp['nm_id'], $q['phrase'], $q['position'], 'selected'];
                    }
                }
            }
            if (!empty($toInsert)) {
                Yii::$app->db->createCommand()->batchInsert(
                    WbCompetitorAnalysis::tableName(),
                    ['company_id', 'source_nm_id', 'competitor_nm_id', 'query_phrase', 'position', 'status'],
                    $toInsert
                )->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error("saveCompetitors failed nmId={$nmId}: " . $e->getMessage(), 'competitor');
            throw $e;
        }
    }

    /**
     * Удаление конкурента из отбора (AJAX POST)
     */
    public function actionRemove()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $nmId = (int)Yii::$app->request->post('nm_id');
        $sourceNmId = (int)Yii::$app->request->post('source_nm_id');

        $deleted = WbCompetitorAnalysis::deleteAll([
            'and',
            ['source_nm_id' => $sourceNmId, 'competitor_nm_id' => $nmId],
            ['status' => 'selected'],
        ]);

        if ($deleted) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Не найдено'];
    }

    /**
     * AI-анализ одного конкурента (AJAX POST)
     */
    public function actionAnalyze()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int)Yii::$app->request->post('id');

        $analysis = WbCompetitorAnalysis::findOne($id);
        if (!$analysis) {
            return ['success' => false, 'error' => 'Не найдено'];
        }

        // Наша карточка
        $ourCard = WbCard::find()->where(['nmID' => $analysis->source_nm_id])->asArray()->one();
        if (!$ourCard) {
            return ['success' => false, 'error' => 'Наша карточка не найдена'];
        }

        // Конкурент
        $compDetail = WbCompetitorDetail::find()->where(['nm_id' => $analysis->competitor_nm_id])->asArray()->one();
        if (!$compDetail) {
            return ['success' => false, 'error' => 'Детали конкурента не найдены (нужно обогащение)'];
        }

        // Все записи анализа для этого конкурента (все фразы)
        $allAnalyses = WbCompetitorAnalysis::find()
            ->where([
                'source_nm_id' => $analysis->source_nm_id,
                'competitor_nm_id' => $analysis->competitor_nm_id,
            ])
            ->andWhere(['not', ['status' => 'removed']])
            ->all();

        $queryPhrases = [];
        foreach ($allAnalyses as $a) {
            $queryPhrases[] = [
                'phrase' => $a->query_phrase,
                'position' => $a->position,
            ];
        }

        $ai = $this->runAiAnalysisWithError($ourCard, $compDetail, $queryPhrases, $analysis->company_id);
        $result = $ai['result'];
        $aiError = $ai['error'];

        if ($result === null) {
            return ['success' => false, 'error' => $aiError ?? 'Ошибка AI'];
        }

        // Сохраняем результат для ВСЕХ фраз этого конкурента
        foreach ($allAnalyses as $a) {
            $a->ai_result = $result;
            $a->status = 'analyzed';
            $a->save(false);
        }

        return ['success' => true, 'result' => $result];
    }

    /**
     * AI-анализ конкурента напрямую по nm_id (для “Ещё не проанализировано”) — AJAX POST
     */
    public function actionAnalyzeDirect()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $sourceNmId = (int)Yii::$app->request->post('source_nm_id');
        $competitorNmId = (int)Yii::$app->request->post('competitor_nm_id');
        if (!$sourceNmId || !$competitorNmId) return ['success' => false, 'error' => 'Недостаточно данных'];

        $ourCard = WbCard::find()->where(['nmID' => $sourceNmId])->asArray()->one();
        if (!$ourCard) return ['success' => false, 'error' => 'Наша карточка не найдена'];
        $compDetail = WbCompetitorDetail::find()->where(['nm_id' => $competitorNmId])->asArray()->one();
        if (!$compDetail) return ['success' => false, 'error' => 'Детали конкурента не найдены'];

        // ищем существующие записи, если нет — создаём
        $existing = WbCompetitorAnalysis::find()
            ->where(['source_nm_id' => $sourceNmId, 'competitor_nm_id' => $competitorNmId])
            ->andWhere(['not', ['status' => 'removed']])
            ->all();
        if (empty($existing)) {
            $cardRow = WbCompetitorCard::find()->where(['source_nm_id' => $sourceNmId, 'nm_id' => $competitorNmId])->orderBy(['position' => SORT_ASC])->asArray()->one();
            $phrase = $cardRow['query_phrase'] ?? 'поиск';
            $pos = $cardRow['position'] ?? 0;
            $cardCompanyId = $ourCard['company_id'] ?? null;
            $companyId = $this->resolveCompanyId($cardCompanyId);
            $model = new WbCompetitorAnalysis();
            $model->company_id = $companyId;
            $model->source_nm_id = $sourceNmId;
            $model->competitor_nm_id = $competitorNmId;
            $model->query_phrase = $phrase;
            $model->position = $pos;
            $model->status = 'selected';
            $model->save(false);
            $existing = [$model];
        }
        $queryPhrases = [];
        foreach ($existing as $a) $queryPhrases[] = ['phrase' => $a->query_phrase, 'position' => $a->position];
        $companyIdForAi = $existing[0]->company_id ?? $this->resolveCompanyId($ourCard['company_id'] ?? null);
        $ai = $this->runAiAnalysisWithError($ourCard, $compDetail, $queryPhrases, $companyIdForAi);
        if ($ai['result'] === null) return ['success' => false, 'error' => $ai['error'] ?? 'Ошибка AI'];
        foreach ($existing as $a) { $a->ai_result = $ai['result']; $a->status = 'analyzed'; $a->save(false); }
        return ['success' => true, 'result' => $ai['result']];
    }

    /**
     * AI-анализ всех отмеченных конкурентов (AJAX POST)
     */
    public function actionAnalyzeAll()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $nmId = (int)Yii::$app->request->post('nm_id');

        $items = WbCompetitorAnalysis::find()
            ->where(['source_nm_id' => $nmId, 'status' => 'selected'])
            ->all();

        if (empty($items)) {
            return ['success' => false, 'error' => 'Нет отмеченных конкурентов для анализа'];
        }

        $ourCard = WbCard::find()->where(['nmID' => $nmId])->asArray()->one();
        if (!$ourCard) {
            return ['success' => false, 'error' => 'Наша карточка не найдена'];
        }

        // Группируем по конкуренту
        $byCompetitor = [];
        foreach ($items as $analysis) {
            $cnid = $analysis->competitor_nm_id;
            if (!isset($byCompetitor[$cnid])) {
                $byCompetitor[$cnid] = [
                    'detail' => null,
                    'phrases' => [],
                    'analyses' => [],
                ];
            }
            $byCompetitor[$cnid]['phrases'][] = [
                'phrase' => $analysis->query_phrase,
                'position' => $analysis->position,
            ];
            $byCompetitor[$cnid]['analyses'][] = $analysis;
        }

        $results = [];
        $errors = [];

        foreach ($byCompetitor as $cnid => $group) {
            $compDetail = WbCompetitorDetail::find()
                ->where(['nm_id' => $cnid])
                ->asArray()
                ->one();

            if (!$compDetail) {
                $errors[] = "nmID {$cnid}: нет данных (нужно обогащение)";
                continue;
            }

            $ai = $this->runAiAnalysisWithError($ourCard, $compDetail, $group['phrases'], $items[0]->company_id ?? null);
            $result = $ai['result'];

            if ($result === null) {
                $errors[] = "nmID {$cnid}: " . ($ai['error'] ?? 'Ошибка AI');
                continue;
            }

            // Сохраняем для всех фраз этого конкурента
            foreach ($group['analyses'] as $a) {
                $a->ai_result = $result;
                $a->status = 'analyzed';
                $a->save(false);
            }

            $results[] = [
                'competitor_nm_id' => $cnid,
                'result' => $result,
            ];
        }

        return [
            'success' => count($results) > 0,
            'analyzed' => count($results),
            'errors' => $errors,
            'results' => $results,
        ];
    }

    /**
     * Сводная рекомендация на основе всех анализов (AJAX POST)
     */
    public function actionSummary()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $nmId = (int)Yii::$app->request->post('nm_id');
        $force = (int)Yii::$app->request->post('force', 0);

        $hasCacheTable = Yii::$app->db->getTableSchema('{{%wb_competitor_summary}}', true) !== null;

        // Защита пересчёта: моложе 30 дней — только админ
        if ($force && $hasCacheTable) {
            $cachedForCheck = WbCompetitorSummary::find()->where(['source_nm_id' => $nmId])->asArray()->one();
            if ($cachedForCheck && !empty($cachedForCheck['created_at'])) {
                $age = (int)floor((time() - strtotime($cachedForCheck['created_at'])) / 86400);
                if ($age < 30 && !Yii::$app->user->can('admin')) {
                    return ['success' => false, 'error' => 'Пересчёт доступен только администратору (рекомендация моложе 30 дней)'];
                }
            }
        }

        // Проверяем кэш — если есть запись, показываем её (пересчёт только по кнопке)
        if (!$force && $hasCacheTable) {
            $cached = WbCompetitorSummary::find()->where(['source_nm_id' => $nmId])->asArray()->one();
            if ($cached && $cached['result']) {
                $result = $this->unwrapResult($cached['result']);
                $rawResp = $cached['raw_ai_response'] ?? null;
                $canRecalc = true;
                $cacheAgeDays = null;
                if (!empty($cached['created_at'])) {
                    $cacheAgeDays = (int)floor((time() - strtotime($cached['created_at'])) / 86400);
                    if ($cacheAgeDays < 30 && !Yii::$app->user->can('admin')) {
                        $canRecalc = false;
                    }
                }
                return [
                    'success' => true,
                    'result' => $result,
                    'cached' => true,
                    'competitor_count' => $cached['competitor_count'],
                    'raw_ai_response' => $rawResp,
                    'cached_at' => $cached['created_at'] ?? null,
                    'cached_model' => $cached['model'] ?? null,
                    'can_recalc' => $canRecalc,
                    'cache_age_days' => $cacheAgeDays,
                ];
            }
        }

        $items = WbCompetitorAnalysis::find()
            ->where(['source_nm_id' => $nmId])
            ->andWhere(['is not', 'ai_result', null])
            ->andWhere(['<>', 'competitor_nm_id', $nmId])
            ->all();

        if (empty($items)) {
            return ['success' => false, 'error' => 'Нет проанализированных конкурентов'];
        }

        $ourCard = WbCard::find()->where(['nmID' => $nmId])->asArray()->one();
        if (!$ourCard) {
            return ['success' => false, 'error' => 'Наша карточка не найдена'];
        }

        // Настройки компании для длины описания — берём из CompanyManager (сессия), fallback на карточку
        $ourCardCompanyId = $ourCard['company_id'] ?? null;
        $companyId = $this->resolveCompanyId($ourCardCompanyId);
        $company = $companyId ? \app\models\Company::findOne($companyId) : null;
        $descMin = $company->seo_desc_min ?? 1500;
        $descMax = $company->seo_desc_max ?? 4000;

        // Собираем все результаты + данные конкурентов
        $compNmIds = [];
        foreach ($items as $item) {
            $compNmIds[$item->competitor_nm_id] = true;
        }
        $compDetailsMap = [];
        if (!empty($compNmIds)) {
            $compDetails = WbCompetitorDetail::find()
                ->where(['nm_id' => array_keys($compNmIds)])
                ->asArray()
                ->all();
            foreach ($compDetails as $cd) {
                $compDetailsMap[$cd['nm_id']] = $cd;
            }
        }

        // Только уникальные конкуренты (1 запись на nm_id) — берём лучшую позицию
        $byCompetitor = [];
        foreach ($items as $item) {
            $nid = $item->competitor_nm_id;
            if (!isset($byCompetitor[$nid]) || $item->position < $byCompetitor[$nid]->position) {
                $byCompetitor[$nid] = $item;
            }
        }
        $summaries = [];
        foreach ($byCompetitor as $item) {
            $result = is_string($item->ai_result) ? $this->safeJsonDecode($item->ai_result) : $item->ai_result;
            if (!is_array($result)) $result = is_string($item->ai_result) ? json_decode($item->ai_result, true) : null;
            if (!is_array($result)) continue;
            $cd = $compDetailsMap[$item->competitor_nm_id] ?? null;
            $summaries[] = [
                'competitor_nm_id' => $item->competitor_nm_id,
                'query' => $item->query_phrase,
                'position' => $item->position,
                'comp_title' => mb_substr($cd['title'] ?? '', 0, 200),
                'comp_desc' => mb_substr($cd['description'] ?? '', 0, 1200),
                'competitor_better' => $result['competitor_better'] ?? [],
                'we_better' => $result['we_better'] ?? [],
                'recommendations' => $result['recommendations'] ?? [],
            ];
        }

        if (empty($summaries)) {
            return ['success' => false, 'error' => 'Нет данных для сводки'];
        }

        // Промпт из компании или дефолтный
        $promptSource = $company ? "default(company#{$company->id} empty)" : "default(no company)";
        if ($company && trim((string)$company->seo_summary_prompt) !== '') {
            $systemPrompt = str_replace(['{DESC_MIN}', '{DESC_MAX}'], [$descMin, $descMax], $company->seo_summary_prompt);
            $promptSource = "company#{$company->id}";
        } else {
            $systemPrompt = <<<EOT
Ты — копирайтер Wildberries. Верни ТОЛЬКО валидный JSON без markdown-обёрток и без пояснений.

### FORMAT (строго такой, без доп. полей):
{"title":"...","description":"...","title_recommendations":["..."],"description_recommendations":["..."],"priority_actions":["..."]}

### ЗАГОЛОВОК — СТРОГО ≤60 символов:
- Посчитай символы. Если >60 — сократи. Это критично, иначе отклоню.
- Структура: 1-2 частотных поисковых запроса + 2-3 атрибута через запятую (формат А5, год 2027, 100 стр, лунный календарь)
- Без стоп-слов: "недатированный", "идеальный", "лучший друг", "источник вдохновения"
- Пример ХОРОШО: "Дневник садовода-огородника 2027 А5, 100 стр, лунный календарь" (52 символа)

### ОПИСАНИЕ — {$descMin}-{$descMax} символов, ОДИН СПЛОШНОЙ ТЕКСТ:
- Оптимальная длина {$descMin}-{$descMax} символов. Перед ответом посчитай длину. Если меньше {$descMin} — допиши пользу и сценарии использования.
- Возьми лучшее из нашей карточки и конкурентов, перефразируй (не копируй дословно).
- Вплети поисковые фразы как ключевые слова (каждую 1-2 раза, естественно, без спама).
- ТТХ (формат, год, страниц, бумага 80 г/м², обложка) вплети ВНУТРИ предложений: "Формат А5 на 100 страниц с плотной бумагой...".

### CRITICAL RULES — нарушение = брак, ответ отклоню:
- ЗАПРЕЩЕНО слово "Характеристики:" в любом регистре и падеже.
- ЗАПРЕЩЕНЫ подзаголовки "Что внутри", "Для кого", "Состав", "Комплектация", "Описание" отдельной строкой.
- ЗАПРЕЩЕН список с "—" или "-" в начале строки. Только сплошной текст абзацами (2-3 абзаца, без маркеров).
- ПЛОХО: "Что внутри:\n— лунный календарь\n— таблицы" 
- ХОРОШО: "Внутри — лунный календарь на 2027 год, таблицы совместимости культур и схемы севооборота, которые помогают..."

Стиль: разговорный, конкретно, без воды. Пиши как для покупателя, а не для SEO-робота.
EOT;
        }

        $userPrompt = "=== ПОИСКОВЫЕ ФРАЗЫ КОНКУРЕНТОВ (ОБЯЗАТЕЛЬНО ИСПОЛЬЗУЙ В ЗАГОЛОВКЕ И ОПИСАНИИ) ===\n";
        $seenQueries = [];
        foreach ($summaries as $s) {
            if (!isset($seenQueries[$s['query']])) {
                $userPrompt .= "- {$s['query']}\n";
                $seenQueries[$s['query']] = true;
            }
        }
        $userPrompt .= "\n=== НАША КАРТОЧКА ===\n";
        $userPrompt .= "Заголовок: " . ($ourCard['title'] ?? '') . "\n";
        $userPrompt .= "Описание: " . ($ourCard['description'] ?? '') . "\n\n";
        $userPrompt .= "=== АНАЛИЗЫ КОНКУРЕНТОВ (" . count($summaries) . " шт) ===\n";
        foreach ($summaries as $i => $s) {
            $userPrompt .= "\n--- Конкурент " . ($i + 1) . " (nmID {$s['competitor_nm_id']}, позиция #{$s['position']}, фраза: {$s['query']}) ---\n";
            $userPrompt .= "Заголовок конкурента: {$s['comp_title']}\n";
            $userPrompt .= "Описание конкурента: {$s['comp_desc']}\n";
            if (!empty($s['competitor_better'])) $userPrompt .= "Лучше у конкурента: " . implode('; ', $s['competitor_better']) . "\n";
            if (!empty($s['we_better'])) $userPrompt .= "Лучше у нас: " . implode('; ', $s['we_better']) . "\n";
            if (!empty($s['recommendations'])) $userPrompt .= "Рекомендации: " . implode('; ', $s['recommendations']) . "\n";
        }
        $userPrompt .= "\nВАЖНО: Сформируй заголовок (до 60 символов!) и описание ({$descMin}-{$descMax} символов), используя поисковые фразы как ключевые слова для индексации.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $summaryModel = $company && $company->seo_summary_model ? $company->seo_summary_model : null;
        if (!$summaryModel && $company && $company->seo_model) $summaryModel = $company->seo_model;
        $summaryMaxTokens = $company && $company->seo_summary_max_tokens ? (int)$company->seo_summary_max_tokens : 4000;
        // снимаем lock сессии — иначе отчёты висят пока AI думает 30с
        if (Yii::$app->session->isActive) Yii::$app->session->close();
        // поддержка списка "model1,model2" — пробуем по очереди
        $summaryModels = $summaryModel ? array_filter(array_map('trim', explode(',', $summaryModel))) : [null];
        $client = Yii::createObject(\app\components\OpenRouterClient::class);
        $response = null;
        foreach ($summaryModels as $tryModel) {
            $response = $client->chat($messages, $tryModel ?: null, null, 0.7, $companyId, $summaryMaxTokens);
            if ($response && isset($response['content'])) break;
            // 404/429 — пробуем следующую
            if ($client->lastError && (str_contains($client->lastError, '404') || str_contains($client->lastError, '429'))) continue;
            break;
        }

        // Логируем запрос и ответ
        $logEntry = [
            'type' => 'summary',
            'nm_id' => $nmId,
            'company_id' => $companyId,
            'prompt_source' => $promptSource,
            'system_prompt' => mb_substr($systemPrompt, 0, 800),
            'system_prompt_len' => mb_strlen($systemPrompt),
            'user_prompt' => mb_substr($userPrompt, 0, 5000),
            'model' => $client->lastModel ?? null,
            'response' => $response ? ($response['content'] ?? null) : null,
            'error' => $client->lastError ?? null,
            'ts' => date('Y-m-d H:i:s'),
        ];
        Yii::warning(json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ai_log');

        if (!$response || !isset($response['content'])) {
            return ['success' => false, 'error' => $client->lastError ?? 'Ошибка AI'];
        }

        $content = trim($response['content']);
        // Сохраняем сырой ответ ДО любой обработки
        $rawResponse = $content;

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) {
            $content = $m[1];
        }
        $decoded = $this->safeJsonDecode($content);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = $this->safeJsonDecode($m[0]);
        }
        if (!is_array($decoded)) {
            $errMsg = json_last_error_msg();
            Yii::warning("Summary JSON decode failed: {$errMsg} | raw_len=" . mb_strlen($content) . " | raw=" . mb_substr($content, 0, 800), 'ai_log');
            // последняя попытка — вытащить title/description регуляркой
            $fallback = $this->fallbackExtractJson($content);
            if ($fallback !== null) {
                Yii::warning("Summary fallbackExtract succeeded: keys=" . implode(',', array_keys($fallback)), 'ai_log');
                $decoded = $fallback;
            } else {
                $decoded = ['raw' => $content];
            }
        }

        Yii::info("Summary AI response keys: " . implode(', ', array_keys($decoded)) . " | has_title=" . (isset($decoded['title']) ? 'Y' : 'N') . " | json_err=" . json_last_error_msg(), 'competitor');

        // Fallback: если модель не верила title/description — генерируем из рекомендаций
        if (empty($decoded['title']) && !empty($decoded['title_recommendations'])) {
            $decoded['title'] = implode(' ', array_slice($decoded['title_recommendations'], 0, 1));
        }
        if (empty($decoded['description']) && !empty($decoded['description_recommendations'])) {
            $decoded['description'] = implode(' ', $decoded['description_recommendations']);
        }

        $toSave = $this->unwrapResult($decoded);

        // Сохраняем в кэш (если таблица есть)
        if ($hasCacheTable) {
            $companyId = $this->resolveCompanyId($ourCard['company_id'] ?? null);
            $existing = WbCompetitorSummary::find()->where(['source_nm_id' => $nmId])->one();
            $attrs = [
                'result' => $toSave,
                'raw_ai_response' => $rawResponse,
                'competitor_count' => count($summaries),
                'model' => $client->lastModel ?? null,
                'suggested_title' => $toSave['title'] ?? null,
                'suggested_description' => $toSave['description'] ?? null,
            ];

            Yii::info("Summary SAVE: toSave keys=" . implode(',', array_keys($toSave)) . " | has_title=" . (isset($toSave['title']) ? 'Y' : 'N'), 'competitor');

            if ($existing) {
                $existing->setAttributes($attrs, false);
                $existing->save(false);
            } else {
                $summary = new WbCompetitorSummary();
                $summary->company_id = $companyId;
                $summary->source_nm_id = $nmId;
                $summary->setAttributes($attrs, false);
                $summary->save(false);
            }
        }

        return ['success' => true, 'result' => $toSave];
    }

    /**
     * Просмотр результатов анализа
     */
    public function actionResults($nm_id)
    {
        $nm_id = (int)$nm_id;

        // Записи анализа (имеющие ai_result или без) — исключаем наш товар
        $items = WbCompetitorAnalysis::find()
            ->where(['source_nm_id' => $nm_id])
            ->andWhere(['not', ['status' => 'removed']])
            ->andWhere(['<>', 'competitor_nm_id', $nm_id])
            ->orderBy(['competitor_nm_id' => SORT_ASC, 'query_phrase' => SORT_ASC])
            ->all();

        $card = WbCard::find()->where(['nmID' => $nm_id])->asArray()->one();

        // Все уникальные конкуренты из wb_competitor_cards для этого товара (исключая наш)
        $allCompNmIds = [];
        $compRows = WbCompetitorCard::find()
            ->select('nm_id, MIN(title) as title, MIN(brand) as brand')
            ->where(['source_nm_id' => $nm_id])
            ->andWhere(['<>', 'nm_id', $nm_id])
            ->groupBy('nm_id')
            ->orderBy('nm_id')
            ->asArray()
            ->all();
        foreach ($compRows as $cr) {
            $allCompNmIds[$cr['nm_id']] = $cr['title'];
        }

        // nm_id из анализа тоже добавляем (исключая наш)
        foreach ($items as $item) {
            if ($item->competitor_nm_id != $nm_id) {
                $allCompNmIds[$item->competitor_nm_id] = null;
            }
        }

        // Данные карточек конкурентов (цена, рейтинг, отзывы) — по первому найденному
        $compCards = [];
        if (!empty($allCompNmIds)) {
            $cards = WbCompetitorCard::find()
                ->where(['nm_id' => array_keys($allCompNmIds)])
                ->orderBy(['nm_id' => SORT_ASC, 'position' => SORT_ASC])
                ->asArray()
                ->all();
            foreach ($cards as $c) {
                if (!isset($compCards[$c['nm_id']])) {
                    $compCards[$c['nm_id']] = $c;
                }
            }
        }

        // Детали конкурентов (описание, картинки)
        $compDetails = [];
        if (!empty($allCompNmIds)) {
            $details = WbCompetitorDetail::find()
                ->where(['nm_id' => array_keys($allCompNmIds)])
                ->asArray()
                ->all();
            foreach ($details as $d) {
                $compDetails[$d['nm_id']] = $d;
            }
        }

        return $this->render('results', [
            'nmId' => $nm_id,
            'items' => $items,
            'allCompNmIds' => array_keys($allCompNmIds),
            'card' => $card,
            'compCards' => $compCards,
            'compDetails' => $compDetails,
        ]);
    }

    /**
     * Формирует промпт и вызывает AI для сравнения карточек
     * @param array $queryPhrases — [['phrase' => '...', 'position' => N], ...]
     */
    private function runAiAnalysis(array $ourCard, array $compDetail, array $queryPhrases, ?int $companyId): ?array
    {
        $res = $this->runAiAnalysisWithError($ourCard, $compDetail, $queryPhrases, $companyId);
        return $res['result'];
    }

    /**
     * Обёртка над runAiAnalysis — возвращает и результат и ошибку
     * @return array{result: ?array, error: ?string}
     */
    private function runAiAnalysisWithError(array $ourCard, array $compDetail, array $queryPhrases, ?int $companyId): array
    {
        $ourTitle = $ourCard['title'] ?? '';
        $ourDesc = $ourCard['description'] ?? '';
        $compTitle = $compDetail['title'] ?? '';
        $compDesc = $compDetail['description'] ?? '';
        // fallback: CompanyManager → user → карточка
        if (!$companyId) $companyId = $this->resolveCompanyId($ourCard['company_id'] ?? null);
        $company = $companyId ? \app\models\Company::findOne($companyId) : null;
        $promptSource = 'default';
        if ($company && trim((string)$company->seo_competitor_prompt) !== '') {
            $systemPrompt = $company->seo_competitor_prompt;
            $promptSource = "company#{$companyId}";
        } else {
            $systemPrompt = $this->defaultCompetitorPrompt();
            $promptSource = "default(company#{$companyId} empty)";
        }
        $userPrompt = "=== ПОИСКОВЫЕ ФРАЗЫ КОНКУРЕНТА ===\n";
        foreach ($queryPhrases as $qp) {
            $userPrompt .= "- \"{$qp['phrase']}\" (позиция #{$qp['position']})\n";
        }
        $userPrompt .= "\n=== НАША КАРТОЧКА ===\nЗаголовок: {$ourTitle}\nОписание: {$ourDesc}\n\n=== КОНКУРЕНТ ===\nЗаголовок: {$compTitle}\nОписание: {$compDesc}\n\nПроанализируй и дай рекомендации с учётом всех фраз.";
        $messages = [['role' => 'system', 'content' => $systemPrompt], ['role' => 'user', 'content' => $userPrompt]];
        if (Yii::$app->session->isActive) Yii::$app->session->close();
        $client = Yii::createObject(\app\components\OpenRouterClient::class);
        $response = $client->chat($messages, null, null, 0.3, $companyId);
        $logEntry = [
            'type' => 'competitor_analysis',
            'source_nm_id' => $ourCard['nmID'] ?? $ourCard['nmId'] ?? null,
            'competitor_nm_id' => $compDetail['nm_id'] ?? null,
            'company_id' => $companyId,
            'prompt_source' => $promptSource,
            'system_prompt' => mb_substr($systemPrompt, 0, 800),
            'system_prompt_len' => mb_strlen($systemPrompt),
            'user_prompt' => mb_substr($userPrompt, 0, 3000),
            'model' => $client->lastModel ?? null,
            'response' => $response ? ($response['content'] ?? null) : null,
            'error' => $client->lastError ?? null,
            'ts' => date('Y-m-d H:i:s'),
        ];
        Yii::warning(json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ai_log');
        if (!$response || !isset($response['content'])) {
            return ['result' => null, 'error' => $client->lastError ?? 'Ошибка AI'];
        }
        $content = trim($response['content']);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) $content = $m[1];
        $decoded = $this->safeJsonDecode($content);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $content, $m)) $decoded = $this->safeJsonDecode($m[0]);
        if (!is_array($decoded)) return ['result' => ['raw' => $content], 'error' => null];
        return ['result' => $decoded, 'error' => null];
    }

    private function defaultCompetitorPrompt(): string
    {
        return <<<'EOT'
Ты — SEO-специалист Wildberries. Сравни две карточки (title + description) и верни ТОЛЬКО JSON без markdown.

ФОРМАТ:
{
  "competitor_better": ["факт 1", "факт 2"],
  "we_better": ["факт 1"],
  "recommendations": ["что конкретно изменить 1"]
}

ПРАВИЛА:
- До 5 пунктов в каждом массиве. Каждый пункт — 1 конкретный факт с цифрами/примерами, а не "лучше описание".
- Сравнивай ТОЛЬКО title и description. Игнорируй цену/отзывы.
- Учитывай поисковые фразы конкурента — они показывают, за счёт чего он в топе.
- Рекомендации — actionable: "Добавить в title 'лунный календарь 2027'", а не "улучшить заголовок".
- Если отличий нет — пиши ["отличий нет"], не выдумывай.
- Отвечай на русском.

ПРИМЕР ХОРОШО:
{"competitor_better":["В title есть 'лунный календарь 2027' + 'А5 100 стр' — закрывает 2 ключа","В описании 80 г/м² и матовая обложка — конкретика"],"we_better":["У нас есть таблицы совместимости культур — у конкурента нет"],"recommendations":["Добавить в title 'А5' и год 2027","Вписать в первые 300 символов описания фразу 'лунный календарь'"]}

ПРИМЕР ПЛОХО: "У конкурента лучше описание" (без факта).
EOT;
    }

    /**
     * Распаковывает результат: обрабатывает двойную вложенность {"raw":"{...}"}
     */
    private function unwrapResult($raw): array
    {
        $result = is_string($raw) ? $this->safeJsonDecode($raw) : $raw;
        if (!is_array($result)) {
            // пробуем как строку с JSON-объектом внутри
            if (is_string($raw) && preg_match('/\{.*\}/s', $raw, $m)) {
                $tmp = $this->safeJsonDecode($m[0]);
                if (is_array($tmp)) return $tmp;
            }
            return ['raw' => (string)$raw];
        }
        // Повторно распаковываем, если результат — обёртка {"raw": "..."} (в т.ч. двойная)
        for ($i = 0; $i < 5; $i++) {
            if (!isset($result['raw']) || !is_string($result['raw']) && !is_array($result['raw'])) {
                break;
            }
            // если в raw есть ещё поля кроме raw — это не обёртка
            if (count($result) !== 1) break;
            $inner = is_string($result['raw']) ? $this->safeJsonDecode($result['raw']) : $result['raw'];
            if (!is_array($inner)) {
                // пробуем вытянуть JSON из строки raw
                if (is_string($result['raw']) && preg_match('/\{.*\}/s', $result['raw'], $m)) {
                    $inner = $this->safeJsonDecode($m[0]);
                }
                if (!is_array($inner)) break;
            }
            $result = $inner;
        }
        return $result;
    }

    private function safeJsonDecode(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) return $decoded;
        $err = json_last_error();
        // чистим BOM
        $clean = ltrim($json, "\xEF\xBB\xBF \t\n\r");
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) return $decoded;
        // Control character error — вырезаем литеральные управляющие символы внутри строк (AI часто ставит перенос строки без \n)
        if ($err === JSON_ERROR_CTRL_CHAR || json_last_error() === JSON_ERROR_CTRL_CHAR) {
            // заменяем литеральные переводы строк внутри JSON-строк на \n
            // сначала удаляем все 0x00-0x08,0x0B,0x0C,0x0E-0x1F
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);
            $decoded = json_decode($clean, true);
            if (is_array($decoded)) return $decoded;
            // пробуем экранировать оставшиеся литеральные переносы
            $clean2 = preg_replace_callback('/:\s*"((?:[^"]|\\")*?)"/s', function ($m) {
                $v = $m[1];
                $v = str_replace(["\r\n", "\r", "\n"], '\n', $v);
                $v = str_replace("\t", '\t', $v);
                return ':"' . $v . '"';
            }, $clean);
            $decoded = json_decode($clean2, true);
            if (is_array($decoded)) return $decoded;
        }
        // пробуем исправить висячие запятые
        $clean = preg_replace('/,\s*([}\]])/', '$1', $clean);
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) return $decoded;
        // последний шанс — вырезать всё до первой { и после последней }
        if (preg_match('/\{.*\}/s', $json, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
            // и с чисткой контролов
            $inner = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $m[0]);
            $decoded = json_decode($inner, true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }

    private function fallbackExtractJson(string $content): ?array
    {
        // вытаскиваем title/description даже из битого JSON
        $out = [];
        if (preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $content, $m)) {
            $out['title'] = json_decode('"' . $m[1] . '"', true) ?? $m[1];
        }
        if (preg_match('/"description"\s*:\s*"((?:[^"\\\\]|\\\\.|[^"])*)"/s', $content, $m)) {
            // берём до следующей ключевой секции title_recommendations
            $descRaw = $m[1];
            // если внутри description есть "title_recommendations" — обрезаем по нему
            if (preg_match('/^(.*)"\s*,\s*"title_recommendations"/s', '"' . $descRaw . '",' . $content, $mm)) {
                // не удалось — берём как есть
            }
            $out['description'] = json_decode('"' . $descRaw . '"', true) ?? $descRaw;
        }
        foreach (['title_recommendations','description_recommendations','priority_actions'] as $k) {
            if (preg_match('/"' . $k . '"\s*:\s*\[(.*?)\]/s', $content, $m)) {
                $arr = json_decode('[' . $m[1] . ']', true);
                if (is_array($arr)) $out[$k] = $arr;
            }
        }
        return !empty($out['title']) || !empty($out['description']) ? $out : null;
    }

    /**
     * Единое получение company_id: CompanyManager (сессия) → user → карточка
     */
    private function resolveCompanyId($fallbackCardCompanyId = null): ?int
    {
        $cmId = null;
        try { $cmId = Yii::$app->companyManager->getCurrentId(); } catch (\Throwable $e) {}
        if ($cmId !== null) return (int)$cmId;
        $uid = Yii::$app->user->identity->company_id ?? null;
        if ($uid !== null) return (int)$uid;
        if ($fallbackCardCompanyId !== null) return (int)$fallbackCardCompanyId;
        return null;
    }
}
