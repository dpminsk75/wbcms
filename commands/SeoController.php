<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\models\WbSeoRun;
use app\models\WbSeoRecommendation;
use app\components\SeoAnalyzerService;

/**
 * SEO рекомендации через OpenRouter.
 *  php yii seo/analyze --days=30 --limit=20 --dry-run=1
 *  php yii seo/analyze --nmID=123456 --days=30
 */
class SeoController extends Controller
{
    public $days = 30;
    public $limit = 20;
    public $nmID = null;
    public $model = null;
    public $dryRun = 0;
    public $antiSpamDays = 14;
    public $verbose = 0;

    public function options($actionID)
    {
        if ($actionID === 'models') {
            return ['freeOnly'];
        }
        return ['days','limit','nmID','model','dryRun','antiSpamDays','verbose'];
    }
    public function optionAliases()
    {
        return ['d'=>'days','l'=>'limit','n'=>'nmID','m'=>'model','v'=>'verbose'];
    }

    public function actionAnalyze()
    {
        $days = max(7, (int)$this->days);
        $globalModel = $this->model ?: (Yii::$app->params['openRouterModel'] ?? 'minimax/minimax-m3:free');
        $globalLimit = max(1, (int)$this->limit);
        if ($this->limit === null || $this->limit === '') $globalLimit = (int)(Yii::$app->params['openRouterDailyLimit'] ?? 20);
        // если лимит передан через CLI — он уже в $this->limit, иначе берем из params
        $globalLimit = max(1, (int)($this->limit ?: Yii::$app->params['openRouterDailyLimit'] ?? 20));
        $globalAntiSpam = max(1, (int)($this->antiSpamDays ?: Yii::$app->params['seoAntiSpamDays'] ?? 14));
        $dateTo = date('Y-m-d', strtotime('-1 day'));
        $dateFrom = date('Y-m-d', strtotime("-$days days"));

        $apiKey = Yii::$app->params['openRouterApiKey'] ?? '';
        if (empty($apiKey) && !$this->dryRun) {
            $this->stderr("openRouterApiKey не задан в params.php — прерываю. Используй --dryRun=1 для теста без ИИ.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $companies = (new \yii\db\Query())->select(['id','name','seo_model','seo_daily_limit','seo_anti_spam_days'])->from('companies')->where(['is_active'=>1])->all();
        if (empty($companies)) {
            $this->stderr("Нет активных компаний\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // если указан конкретный nmID — обрабатываем только его
        if ($this->nmID) {
            $nmID = (int)$this->nmID;
            $cardCompany = (new \yii\db\Query())->select(['company_id'])->from('wbcards')->where(['nmID'=>$nmID])->scalar();
            if (!$cardCompany) {
                $this->stderr("nmID $nmID не найден в wbcards\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $companies = array_values(array_filter($companies, fn($c)=> (int)$c['id']===(int)$cardCompany));
            if (empty($companies)) {
                $companies = [['id'=>(int)$cardCompany,'name'=>"company $cardCompany"]];
            }
            // для точечного прогона игнорируем anti-spam
            $antiSpam = 0;
        }

        $svc = new SeoAnalyzerService();
        $totalDone = 0;

        foreach ($companies as $company) {
            $cid = (int)$company['id'];
            $cname = $company['name'];
            // per-company overrides с фолбэком на глобальные
            $model = $this->model ?: ($company['seo_model'] ?: $globalModel);
            $limit = $company['seo_daily_limit'] ? max(1,(int)$company['seo_daily_limit']) : $globalLimit;
            // если CLI явно передал limit — он уже в $this->limit, приоритет CLI
            if ($this->limit && (int)$this->limit !== $globalLimit) $limit = max(1,(int)$this->limit);
            $antiSpam = $company['seo_anti_spam_days'] ? max(1,(int)$company['seo_anti_spam_days']) : $globalAntiSpam;
            if ($this->antiSpamDays && (int)$this->antiSpamDays !== $globalAntiSpam) $antiSpam = max(1,(int)$this->antiSpamDays);
            $this->stdout("\n=== Компания $cname (id=$cid) период $dateFrom..$dateTo model=$model limit=$limit dryRun={$this->dryRun} ===\n", Console::FG_CYAN);

            $run = new WbSeoRun();
            $run->company_id = $cid;
            $run->started_at = date('Y-m-d H:i:s');
            $run->date_from = $dateFrom;
            $run->date_to = $dateTo;
            $run->model = $model;
            $run->daily_limit = $limit;
            $run->days_window = $days;
            $run->processed = 0;
            $run->skipped = 0;
            $run->errors = 0;
            $run->status = 'running';
            $run->save(false);

            $nmList = $this->getRankedNmIds($cid, $dateFrom, $dateTo, $limit, $antiSpam, $this->nmID ? (int)$this->nmID : null);

            if (empty($nmList)) {
                $this->stdout("Нет товаров для анализа (все покрыты за $antiSpam дней или нет продаж).\n", Console::FG_YELLOW);
                $run->finished_at = date('Y-m-d H:i:s');
                $run->status = 'done';
                $run->save(false);
                continue;
            }

            $this->stdout("К анализу: " . implode(', ', array_column($nmList,'nm_id')) . " (".count($nmList)." шт)\n");

            foreach ($nmList as $row) {
                $nmID = (int)$row['nm_id'];
                $totalQnt = (int)($row['total_qnt'] ?? 0);
                $this->stdout("  nmID $nmID (продаж $totalQnt за $days дн) ... ", Console::FG_YELLOW);

                if ($this->dryRun) {
                    $built = $svc->buildPrompt($nmID, $cid, $dateFrom, $dateTo);
                    if (!$built) {
                        $this->stdout("SKIP: нет карточки/фраз\n", Console::FG_RED);
                        $run->skipped++;
                        continue;
                    }
                    $phraseCount = count($built['signals']['top_clicks'] ?? []);
                    $this->stdout("DRY-RUN ok, фраз $phraseCount, title=\"".mb_substr($built['card']->title ?? '',0,40)."\"\n", Console::FG_GREEN);
                    if ($this->verbose) {
                        $this->stdout("----- PROMPT SOURCE: " . ($built['prompt_source'] ?? 'unknown') . " ({$built['descMin']}-{$built['descMax']}) -----\n", Console::FG_CYAN);
                        $this->stdout("----- PROMPT (system) -----\n" . $built['messages'][0]['content'] . "\n", Console::FG_GREY);
                        $this->stdout("----- PROMPT (user JSON) -----\n" . $built['messages'][1]['content'] . "\n", Console::FG_GREY);
                        $this->stdout("----- SIGNALS -----\n" . json_encode($built['signals'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n", Console::FG_GREY);
                    }
                    $run->processed++;
                    // не спим в dry-run
                    continue;
                }

                // verbose: показать промпт до запроса
                $builtForLog = null;
                if ($this->verbose) {
                    $builtForLog = $svc->buildPrompt($nmID, $cid, $dateFrom, $dateTo);
                    if ($builtForLog) {
                        $this->stdout("----- PROMPT SOURCE: " . ($builtForLog['prompt_source'] ?? 'unknown') . " ({$builtForLog['descMin']}-{$builtForLog['descMax']}) -----\n", Console::FG_CYAN);
                        $this->stdout("----- PROMPT (system) -----\n" . $builtForLog['messages'][0]['content'] . "\n", Console::FG_GREY);
                        $this->stdout("----- PROMPT (user JSON) -----\n" . $builtForLog['messages'][1]['content'] . "\n", Console::FG_GREY);
                    }
                }
                $this->stdout("  → отправляю к OpenRouter (ожидание до 60с)...\n");
                @flush();
                $res = $svc->generate($nmID, $cid, $dateFrom, $dateTo, $model);
                $this->stdout("  ← ответ получен\n"); @flush();
                if (!$res) {
                    $err = $svc->lastError ?: 'unknown error';
                    $this->stdout("ERROR: $err\n", Console::FG_RED);
                    if ($this->verbose) {
                        $this->stdout("----- ERROR DUMP -----\n$err\n", Console::FG_RED);
                    }
                    $run->errors++;
                    // при фолбэке уже перебрали все модели — не ждём долго
                    sleep(2);
                    continue;
                }
                // показать какой моделью сработало при фолбэке
                if (!empty($res['_used_model']) && $res['_used_model'] !== $model) {
                    $this->stdout("  (used model {$res['_used_model']}) ", Console::FG_GREY);
                }
                if ($this->verbose) {
                    $this->stdout("----- RESPONSE raw -----\n" . ($res['raw_content'] ?? '') . "\n", Console::FG_CYAN);
                    $this->stdout("----- PARSED -----\n" . json_encode([
                        'new_title'=>$res['new_title']??'',
                        'new_description'=>$res['new_description']??'',
                        'rationale'=>$res['rationale']??'',
                        'keywords_added'=>$res['keywords_added']??[],
                        'confidence'=>$res['confidence']??null,
                    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n", Console::FG_CYAN);
                    $this->stdout("tokens prompt={$res['prompt_tokens']} completion={$res['completion_tokens']}\n", Console::FG_GREY);
                }

                $card = $res['_card'];
                $now = date('Y-m-d H:i:s');
                $rec = new WbSeoRecommendation();
                $rec->run_id = $run->id;
                $rec->company_id = $cid;
                $rec->nmID = $nmID;
                $rec->old_title = $card->title;
                $rec->old_description = $card->description;
                $rec->new_title = $res['new_title'];
                $rec->new_description = $res['new_description'];
                $rec->rationale = $res['rationale'] ?? null;
                $rec->keywords_added = isset($res['keywords_added']) ? json_encode($res['keywords_added'], JSON_UNESCAPED_UNICODE) : null;
                $rec->keywords_removed = isset($res['keywords_removed']) ? json_encode($res['keywords_removed'], JSON_UNESCAPED_UNICODE) : null;
                $rec->confidence = isset($res['confidence']) ? (float)$res['confidence'] : null;
                $rec->model = $res['_used_model'] ?? $model;
                $rec->prompt_tokens = $res['prompt_tokens'];
                $rec->completion_tokens = $res['completion_tokens'];
                $rec->raw_json = json_encode(['prompt'=>$res['_userData'],'response'=>$res['raw'],'raw_content'=>$res['raw_content']], JSON_UNESCAPED_UNICODE);
                $rec->status = 'new';
                $rec->is_requeued = 0;
                $rec->created_at = $now;
                $rec->updated_at = $now;
                if (!$rec->save()) {
                    $this->stdout("DB ERROR: " . json_encode($rec->errors, JSON_UNESCAPED_UNICODE) . "\n", Console::FG_RED);
                    $run->errors++;
                } else {
                    $this->stdout("OK -> rec #{$rec->id} \"".mb_substr($res['new_title'],0,50)."\" conf=".($res['confidence']??'?')."\n", Console::FG_GREEN);
                    $run->processed++;
                    $totalDone++;
                }

                // пауза между запросами к OpenRouter (free tier ~20/min)
                if ($limit > 1) {
                    sleep(4);
                }
            }

            $run->finished_at = date('Y-m-d H:i:s');
            // если упёрся в лимит но есть ещё непокрытые — статус limit_reached
            $remaining = $this->countRemaining($cid, $dateFrom, $dateTo, $antiSpam);
            $run->status = $remaining > 0 ? 'limit_reached' : 'done';
            $run->save(false);
            $this->stdout("Готово по компании $cname: processed={$run->processed} skipped={$run->skipped} errors={$run->errors} remaining=$remaining status={$run->status}\n", Console::FG_CYAN);
        }

        $this->stdout("\nВсего рекомендаций создано: $totalDone\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Синхронизировать wb_seo_model с OpenRouter (добавляет новые :free).
     * php yii seo/sync-models
     */
    public function actionSyncModels()
    {
        $or = Yii::createObject(\app\components\OpenRouterClient::class);
        $res = $or->getModels();
        if (!$res) {
            $this->stderr("Ошибка: " . ($or->lastError ?: 'unknown') . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $free = $res['free'];
        $now = date('Y-m-d H:i:s');
        $added = 0; $updated = 0;
        foreach ($free as $m) {
            $id = $m['id'] ?? null;
            if (!$id) continue;
            $exists = \app\models\WbSeoModel::findOne(['model_id'=>$id]);
            if (!$exists) {
                $row = new \app\models\WbSeoModel();
                $row->model_id = $id;
                $row->title = $m['name'] ?? $id;
                $row->is_active = 1;
                $row->priority = 500;
                $row->context_length = $m['context_length'] ?? null;
                $row->created_at = $now;
                $row->updated_at = $now;
                $row->save(false);
                $added++;
            } else {
                $exists->title = $m['name'] ?? $exists->title;
                $exists->context_length = $m['context_length'] ?? $exists->context_length;
                $exists->updated_at = $now;
                $exists->save(false);
                $updated++;
            }
        }
        $this->stdout("Синхронизировано free: " . count($free) . " (добавлено $added, обновлено $updated)\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Статус ротации: приоритеты, кулдауны, ошибки.
     * php yii seo/model-status
     */
    public function actionModelStatus()
    {
        $rows = \app\models\WbSeoModel::find()->orderBy(['priority'=>SORT_ASC])->all();
        if (empty($rows)) {
            $this->stdout("Таблица wb_seo_model пуста — запусти migrate и seo/sync-models\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }
        $this->stdout(sprintf("%-4s %-45s %-6s %-4s %-4s %-4s %-20s %s\n", "ID","model_id","active","prio","ok","err","cooldown_until","last_error"), Console::FG_CYAN);
        foreach ($rows as $r) {
            $cd = $r->cooldown_until ?: '-';
            $err = mb_substr($r->last_error ?? '-',0,40);
            $this->stdout(sprintf("%-4d %-45s %-6d %-4d %-4d %-4d %-20s %s\n",
                $r->id, $r->model_id, $r->is_active, $r->priority, $r->success_count, $r->error_count, $cd, $err));
        }
        return ExitCode::OK;
    }

    /**
     * Показать бесплатные модели OpenRouter.
     * php yii seo/models --free-only=1
     * php yii seo/models --free-only=0
     */
    public $freeOnly = 1;
    public function actionModels()
    {
        $or = Yii::createObject(\app\components\OpenRouterClient::class);
        $res = $or->getModels();
        if (!$res) {
            $this->stderr("Ошибка: " . ($or->lastError ?: 'unknown') . "\n", \yii\helpers\Console::FG_RED);
            return \yii\console\ExitCode::UNSPECIFIED_ERROR;
        }
        $list = $this->freeOnly ? $res['free'] : $res['all'];
        $this->stdout("Всего моделей: " . count($res['all']) . ", free: " . count($res['free']) . "\n", \yii\helpers\Console::FG_CYAN);
        if ($this->freeOnly) {
            $this->stdout("Бесплатные (:free) — используй точный id в --model:\n", \yii\helpers\Console::FG_GREEN);
        }
        foreach ($list as $m) {
            $id = $m['id'] ?? '?';
            $name = $m['name'] ?? '';
            $ctx = $m['context_length'] ?? '?';
            $pricing = $m['pricing'] ?? [];
            $price = isset($pricing['prompt']) ? "prompt={$pricing['prompt']} compl={$pricing['completion']}" : '';
            $this->stdout(sprintf("  %-55s ctx=%-6s %s  %s\n", $id, $ctx, $price, $name));
        }
        if ($this->freeOnly && empty($list)) {
            $this->stdout("Free не найдено — попробуй без фильтра: php yii seo/models --free-only=0\n", \yii\helpers\Console::FG_YELLOW);
        }
        return \yii\console\ExitCode::OK;
    }

    /**
     * Ранжированный список nmID по продажам agg_daily_summary.
     */
    private function getRankedNmIds(int $companyId, string $dateFrom, string $dateTo, int $limit, int $antiSpamDays, ?int $singleNmID = null): array
    {
        // агрегируем продажи за окно, но для sdate берём до вчера (dateTo)
        $aggFrom = date('Y-m-d', strtotime($dateFrom));
        $aggTo = $dateTo; // уже yesterday

        if ($singleNmID) {
            return [['nm_id'=>$singleNmID,'total_qnt'=>0]];
        }

        $db = Yii::$app->db;

        // подзапрос последние рекомендации для anti-spam
        $excludeSql = '';
        $params = [':cid'=>$companyId, ':aggFrom'=>$aggFrom, ':aggTo'=>$aggTo];
        if ($antiSpamDays > 0) {
            $spam = (int)$antiSpamDays;
            $excludeSql = " AND w.nmID NOT IN (
                SELECT nmID FROM wb_seo_recommendation 
                WHERE company_id=:cid2 AND created_at >= DATE_SUB(NOW(), INTERVAL $spam DAY) AND is_requeued=0
            )";
            $params[':cid2'] = $companyId;
        }

        // основной ранжир: продажи + активные карточки
        $sql = "
            SELECT w.nmID as nm_id, COALESCE(s.total_qnt,0) as total_qnt,
                   COALESCE(r.is_requeued,0) as is_requeued
            FROM wbcards w
            LEFT JOIN (
                SELECT nm_id, SUM(qnt) as total_qnt
                FROM agg_daily_summary
                WHERE company_id=:cid AND sdate BETWEEN :aggFrom AND :aggTo
                GROUP BY nm_id
            ) s ON s.nm_id = w.nmID
            LEFT JOIN (
                SELECT nmID, MAX(is_requeued) as is_requeued
                FROM wb_seo_recommendation
                WHERE company_id=:cid2 AND is_requeued=1 AND status='new'
                GROUP BY nmID
            ) r ON r.nmID = w.nmID
            WHERE w.company_id=:cid AND w.is_active=1
            $excludeSql
            ORDER BY is_requeued DESC, total_qnt DESC, w.nmID DESC
            LIMIT :lim
        ";
        // если :cid2 не нужен (antiSpam=0) — заменим
        if ($antiSpamDays === 0) {
            $sql = str_replace(':cid2', ':cid', $sql);
            unset($params[':cid2']);
        }
        $params[':lim'] = $limit;

        return $db->createCommand($sql, $params)->queryAll();
    }

    private function countRemaining(int $companyId, string $dateFrom, string $dateTo, int $antiSpamDays): int
    {
        $params = [':cid'=>$companyId];
        $excludeSql = '';
        if ($antiSpamDays > 0) {
            $spam = (int)$antiSpamDays;
            $excludeSql = " AND w.nmID NOT IN (
                SELECT nmID FROM wb_seo_recommendation 
                WHERE company_id=:cid2 AND created_at >= DATE_SUB(NOW(), INTERVAL $spam DAY) AND is_requeued=0
            )";
            $params[':cid2']=$companyId;
        }
        $sql = "SELECT COUNT(*) FROM wbcards w
                WHERE w.company_id=:cid AND w.is_active=1 $excludeSql";
        return (int)Yii::$app->db->createCommand($sql, $params)->queryScalar();
    }
}
