<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Быстрый bench бесплатных моделей OpenRouter.
 *  php yii bench/free
 *  php yii bench/free --limit=6 --timeout=30
 *  php yii bench/free --model=google/gemma-4-31b-it:free
 */
class BenchController extends Controller
{
    public $limit = 0;
    public $timeout = 30;
    public $model = null;

    public function options($actionID)
    {
        return ['limit', 'timeout', 'model'];
    }

    public function optionAliases()
    {
        return ['l' => 'limit', 't' => 'timeout', 'm' => 'model'];
    }

    public function actionFree()
    {
        $or = Yii::createObject(\app\components\OpenRouterClient::class);
        $or->timeout = (int)$this->timeout;
        $or->maxRetries = 0; // для bench без ретраев — чистая скорость

        // 1) список free
        $this->stdout("→ Получаю список моделей OpenRouter...\n", Console::FG_CYAN);
        $res = $or->getModels();
        if (!$res) {
            $this->stderr("Ошибка getModels: " . ($or->lastError ?: 'unknown') . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $free = array_values(array_filter($res['free'], fn($m) => !in_array($m['id'] ?? '', ['google/lyria-3-clip-preview','openrouter/free'], true)));
        if (!$this->model) $this->stdout("Всего: " . count($res['all']) . ", free: " . count($free) . " (lyria-3-clip-preview, openrouter/free исключены)\n", Console::FG_GREEN);

        // если указана конкретная модель — тестируем только её (даже если не в free)
        if ($this->model) {
            $found = array_values(array_filter($res['all'], fn($m) => $m['id'] === $this->model));
            if (!empty($found)) {
                $free = $found;
            } else {
                $free = [['id' => $this->model, 'context_length' => '?']];
            }
            $this->stdout("Тест одной модели: {$this->model}\n", Console::FG_CYAN);
        } else if ($this->limit) {
            // берём первые N для bench (чтобы не DDoSить free tier), 0 = все
            $free = array_slice($free, 0, max(1, (int)$this->limit));
        }

        $this->stdout("Тестирую " . count($free) . " моделей простым JSON-промптом (без ретраев, timeout={$this->timeout}с)...\n\n", Console::FG_YELLOW);

        // простой промпт — как в конкуренте/сводке, но короткий
        $messages = [
            ['role' => 'system', 'content' => 'Верни ТОЛЬКО валидный JSON без markdown: {"ok":true,"text":"привет"} Все переносы как \n.'],
            ['role' => 'user', 'content' => 'Верни JSON {"ok":true,"text":"тест скорости"}'],
        ];

        $results = [];
        $ctxMap = array_column($free, 'context_length', 'id');
        foreach ($free as $idx => $m) {
            $id = $m['id'];
            $ctx = $m['context_length'] ?? '?';
            $this->stdout(sprintf("[%d/%d] %-55s ctx=%-7s ... ", $idx + 1, count($free), $id, $ctx), Console::FG_CYAN);
            @flush();
            $t0 = microtime(true);
            $resp = $or->chat($messages, $id, null, 0.3);
            $dt = microtime(true) - $t0;
            $status = $or->lastStatus;
            $err = $or->lastError;

            $ok = false;
            $validJson = false;
            $contentPreview = '';
            if ($resp && isset($resp['content'])) {
                $contentPreview = mb_substr(trim($resp['content']), 0, 80);
                $j = json_decode(trim($resp['content']), true);
                if (!is_array($j) && preg_match('/\{.*\}/s', $resp['content'], $mm)) $j = json_decode($mm[0], true);
                $validJson = is_array($j) && isset($j['ok']);
                $ok = $validJson;
            }

            $results[] = [
                'id' => $id,
                'ctx' => $ctx,
                'ok' => $ok,
                'validJson' => $validJson,
                'time' => $dt,
                'status' => $status,
                'error' => $err,
                'content' => $contentPreview,
                'prompt_tokens' => $resp['prompt_tokens'] ?? null,
                'completion_tokens' => $resp['completion_tokens'] ?? null,
            ];

            if ($ok) {
                $this->stdout(sprintf("OK %.2fs json=%s\n", $dt, $validJson ? 'yes' : 'no'), Console::FG_GREEN);
            } else {
                $shortErr = $err ? $err : 'no content';
                $this->stdout(sprintf("FAIL %.2fs HTTP=%s %s\n", $dt, $status ?? '-', $shortErr), Console::FG_RED);
            }

            // пауза чтобы не словить 429 на free tier
            if ($idx < count($free) - 1) sleep(2);
        }

        // итоги
        $this->stdout("\n" . str_repeat('=', 80) . "\n", Console::FG_CYAN);
        $this->stdout("ИТОГИ (сортировка: успешные → быстрее):\n", Console::FG_CYAN);
        usort($results, function ($a, $b) {
            if ($a['ok'] !== $b['ok']) return $b['ok'] <=> $a['ok'];
            if ($a['ok']) return $a['time'] <=> $b['time'];
            return ($a['status'] ?? 999) <=> ($b['status'] ?? 999);
        });

        $this->stdout(sprintf("%-4s %-45s %-8s %-6s %-7s %-6s %s\n", "N", "model_id", "ctx", "OK", "time", "HTTP", "error/content"), Console::FG_GREY);
        foreach ($results as $i => $r) {
            $color = $r['ok'] ? Console::FG_GREEN : Console::FG_RED;
            $this->stdout(sprintf("%-4d %-45s %-8s %-6s %-7.2f %-6s %s\n",
                $i + 1, $r['id'], $r['ctx'] ?? '?', $r['ok'] ? 'YES' : 'NO', $r['time'], $r['status'] ?? '-', $r['ok'] ? $r['content'] : ($r['error'] ?? '')
            ), $color);
        }

        // --- обновляем базу wb_seo_model по результатам bench ---
        $this->stdout("\nОбновляю базу wb_seo_model по результатам bench...\n", Console::FG_CYAN);
        $updated = 0;
        foreach ($results as $r) {
            $modelRow = \app\models\WbSeoModel::findOne(['model_id' => $r['id']]);
            $err = $r['error'] ?? '';
            $is404 = str_contains($err, '404') || ($r['status'] ?? 0) == 404 || str_contains($err, 'unavailable for free');
            $is429 = str_contains($err, '429') || str_contains($err, 'rate limited');
            if ($is404) {
                if (!$modelRow) continue;
                $modelRow->is_active = 0;
                $modelRow->last_error = 'bench: 404 not free ' . date('Y-m-d H:i');
                $modelRow->save(false);
                $this->stdout("  {$r['id']} -> deactivated (404)\n", Console::FG_YELLOW);
                $updated++;
            } elseif ($is429) {
                if (!$modelRow) continue;
                $modelRow->markError($err, true);
                $this->stdout("  {$r['id']} -> cooldown 429\n", Console::FG_YELLOW);
                $updated++;
            } elseif ($r['ok']) {
                if (!$modelRow) {
                    $modelRow = new \app\models\WbSeoModel();
                    $modelRow->model_id = $r['id'];
                    $modelRow->title = $r['id'];
                    $modelRow->is_active = 1;
                    $modelRow->priority = 20;
                    $modelRow->created_at = date('Y-m-d H:i:s');
                    $modelRow->updated_at = date('Y-m-d H:i:s');
                    $modelRow->save(false);
                    $this->stdout("  {$r['id']} -> added (was not in table)\n", Console::FG_GREEN);
                }
                $modelRow->markSuccess();
                if ($r['time'] < 5) {
                    // до 5с — выбираем по качеству, а не по времени
                    $prio = $this->qualityPriority($r['id'], $r['time']);
                    $modelRow->priority = min(100, max(10, $prio));
                    $modelRow->save(false);
                } elseif ($r['time'] < 15) {
                    $modelRow->priority = min(100, max(20, (int)($r['time'] * 5)));
                    $modelRow->save(false);
                }
                $updated++;
            } else {
                if (!$modelRow) continue;
                $modelRow->markError($err, false);
                $updated++;
            }
        }
        if ($updated) $this->stdout("Обновлено в базе: $updated\n", Console::FG_GREEN);

        // рекомендация — до 5с сортируем по качеству (ctx/pro), а не по времени
        $best = array_values(array_filter($results, fn($r) => $r['ok']));
        usort($best, fn($a,$b) => $this->qualityPriority($a['id'], $a['time'], $a['ctx'] ?? null) <=> $this->qualityPriority($b['id'], $b['time'], $b['ctx'] ?? null));
        $this->stdout("\n" . str_repeat('-', 80) . "\n", Console::FG_CYAN);
        if (!empty($best)) {
            $top = array_slice($best, 0, 3);
            $this->stdout("РЕКОМЕНДАЦИЯ — ставь в companies.seo_model (приоритет 1..3):\n", Console::FG_GREEN);
            foreach ($top as $k => $r) {
                $this->stdout(sprintf(" %d. %s (%.2fс)\n", $k + 1, $r['id'], $r['time']), Console::FG_GREEN);
            }
            $this->stdout("\nДля замены minimax:\n  UPDATE companies SET seo_model='{$top[0]['id']}' WHERE id=1;\n", Console::FG_YELLOW);
            $this->stdout("Затем: php yii seo/model-status\n", Console::FG_YELLOW);
        } else {
            $this->stdout("РЕКОМЕНДАЦИЯ: ни одна модель не ответила валидным JSON. Попробуй --limit=3 или --model=<id> и проверь ключ OpenRouter.\n", Console::FG_RED);
            $cnt404 = count(array_filter($results, fn($r) => str_contains($r['error'] ?? '', '404')));
            $cnt429 = count(array_filter($results, fn($r) => str_contains($r['error'] ?? '', '429')));
            if ($cnt404) $this->stdout("  404 — модель недоступна для free (как minimax) — исключай.\n", Console::FG_YELLOW);
            if ($cnt429) $this->stdout("  429 — rate limit free tier, повтори через минуту с --limit=3.\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    /**
     * Проверка по расписанию: новые free + отвал текущей модели → Telegram
     *  php yii bench/check
     *  Ставь в cron: 0 9 * * * /usr/bin/php /var/www/wb/wbcms/yii bench/check
     */
    public function actionCheck()
    {
        $or = Yii::createObject(\app\components\OpenRouterClient::class);
        $res = $or->getModels();
        if (!$res) {
            $this->stderr("getModels failed: " . ($or->lastError ?? 'unknown') . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $free = array_values(array_filter($res['free'], fn($m) => ($m['id'] ?? '') !== 'google/lyria-3-clip-preview'));
        $freeIds = array_column($free, 'id');
        $freeMap = array_column($free, null, 'id');

        // новые модели (нет в wb_seo_model)
        $existingIds = \app\models\WbSeoModel::find()->select('model_id')->column();
        $newIds = array_values(array_diff($freeIds, $existingIds));

        // отвал текущей модели из companies.seo_model (поддержка "a,b")
        $companies = (new \yii\db\Query())->select(['id','name','seo_model'])->from('companies')->where(['is_active'=>1])->all();
        $activeIds = \app\models\WbSeoModel::find()->where(['is_active'=>1])->select('model_id')->column();
        $failedCompanies = [];
        foreach ($companies as $c) {
            foreach (['seo_model'] as $field) {
                $raw = $c[$field] ?? '';
                $parts = array_filter(array_map('trim', explode(',', $raw)));
                if (empty($parts)) $parts = [$raw];
                foreach ($parts as $mid) {
                    if (!$mid) continue;
                    if (!in_array($mid, $freeIds, true)) {
                        $failedCompanies[] = $c['name'] . " ({$field}={$mid}) — нет в free";
                    } elseif (!in_array($mid, $activeIds, true)) {
                        $row = \app\models\WbSeoModel::findOne(['model_id'=>$mid]);
                        if ($row && $row->cooldown_until && strtotime($row->cooldown_until) > time()) {
                            $failedCompanies[] = $c['name'] . " ({$field}={$mid}) — в cooldown до {$row->cooldown_until}";
                        } elseif ($row && !$row->is_active) {
                            $failedCompanies[] = $c['name'] . " ({$field}={$mid}) — деактивирована";
                        }
                    }
                }
            }
        }

        // добавляем новые в wb_seo_model сразу (как sync-models)
        if (!empty($newIds)) {
            $now = date('Y-m-d H:i:s');
            foreach ($newIds as $nid) {
                $m = $freeMap[$nid] ?? [];
                $row = new \app\models\WbSeoModel();
                $row->model_id = $nid;
                $row->title = $m['name'] ?? $nid;
                $row->is_active = 1;
                $row->priority = 500;
                $row->context_length = $m['context_length'] ?? null;
                $row->created_at = $now;
                $row->updated_at = $now;
                $row->save(false);
            }
        }
        $messages = [];
        if (!empty($newIds)) {
            $lines = ["🆕 <b>Новые free модели OpenRouter</b> (" . count($newIds) . "):"];
            foreach ($newIds as $nid) {
                $m = $freeMap[$nid] ?? [];
                $seo = $this->isSeoSuitable($nid);
                $tag = $seo ? '✅ SEO' : '⚠️ не SEO';
                $name = htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $lines[] = "• <code>{$nid}</code> ctx=" . ($m['context_length'] ?? '?') . " — {$tag} — " . htmlspecialchars($this->seoHint($nid), ENT_QUOTES, 'UTF-8') . ($name ? " ({$name})" : "");
                if ($seo) {
                    $selfDesc = $this->askModelAboutSeo($nid);
                    if ($selfDesc) $lines[] = "  🤖 <i>" . htmlspecialchars(mb_substr($selfDesc, 0, 400), ENT_QUOTES, 'UTF-8') . "</i>";
                    else $lines[] = "  🤖 <i>не ответила на тест SEO</i>";
                }
            }
            $messages[] = implode("\n", $lines);
        }
        if (!empty($failedCompanies)) {
            $lines = ["🚨 <b>Отвалилась текущая модель</b>:"];
            foreach ($failedCompanies as $f) $lines[] = "• " . htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
            // рекомендация из активных — с описанием модели
            $best = \app\models\WbSeoModel::getActiveOrdered();
            $best = array_values(array_filter($best, fn($r) => $this->isSeoSuitable($r->model_id)));
            if (!empty($best)) {
                $tops = array_slice($best, 0, 2);
                $lines[] = "";
                $lines[] = "💡 <b>Рекомендации</b>:";
                foreach ($tops as $idx => $top) {
                    $m = $freeMap[$top->model_id] ?? [];
                    $name = htmlspecialchars($m['name'] ?? $top->title ?? '', ENT_QUOTES, 'UTF-8');
                    $lines[] = ($idx+1) . ". <code>{$top->model_id}</code> (prio {$top->priority}, ctx " . ($top->context_length ?? '?') . ")" . ($name ? " — {$name}" : "");
                    $selfDesc = $this->askModelAboutSeo($top->model_id);
                    if ($selfDesc) $lines[] = "   🤖 <i>" . htmlspecialchars(mb_substr($selfDesc, 0, 300), ENT_QUOTES, 'UTF-8') . "</i>";
                    else $lines[] = "   " . htmlspecialchars($this->seoHint($top->model_id), ENT_QUOTES, 'UTF-8');
                }
                // кнопка — по первому
                $top = $tops[0];
            }
            $messages[] = implode("\n", $lines);
        }

        if (empty($messages)) {
            $this->stdout("check OK: новых нет, текущие живы (" . count($free) . " free)\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        foreach ($messages as $idx => $msg) {
            $this->stdout($msg . "\n\n", Console::FG_YELLOW);
            // для сообщения об отвале — кнопки по каждой из лучших (по опросу модели)
            $markup = null;
            if (str_contains($msg, 'Отвалилась текущая модель') && !empty($best)) {
                $base = Yii::$app->params['telegramApplyBaseUrl'] ?? (Yii::$app->params['openRouterReferer'] ?? 'https://wbcms.local');
                $secret = Yii::$app->params['telegramApplySecret'] ?? Yii::$app->params['cookieValidationKey'] ?? 'CR9TO_EK2jT--v-l06kbS9Q8GrxRgp0n';
                $expires = time() + 7 * 86400;
                $rows = [];
                foreach (array_slice($best, 0, 2) as $b) {
                    $mid = $b->model_id;
                    $token = hash_hmac('sha256', $mid . ':' . $expires, $secret);
                    $url = rtrim($base, '/') . '/seo/apply-model?model_id=' . urlencode($mid) . '&token=' . $token . '&expires=' . $expires;
                    $rows[] = [['text' => '✅ ' . $mid, 'url' => $url]];
                }
                $markup = ['inline_keyboard' => $rows];
            }
            $this->sendTelegram($msg, $markup);
        }
        return ExitCode::OK;
    }

    /**
     * Тест Hugging Face моделей (что есть сегодня)
     *  php yii bench/hf
     *  php yii bench/hf --model=mistralai/Mistral-7B-Instruct-v0.3
     */
    public function actionHf()
    {
        $hf = Yii::createObject(\app\components\HuggingFaceClient::class);
        $hf->timeout = (int)$this->timeout;

        // список популярных — топ загрузок
        $this->stdout("→ Получаю список HF моделей...\n", Console::FG_CYAN);
        $models = $hf->getModels(10);
        if (!$models) {
            $this->stderr("HF getModels fail: " . ($hf->lastError ?? 'unknown') . "\n", Console::FG_RED);
        } else {
            $this->stdout("Топ HF text-generation (" . count($models) . "):\n", Console::FG_GREEN);
            foreach (array_slice($models, 0, 10) as $m) {
                $this->stdout(sprintf("  %-50s downloads=%-8s likes=%s\n", $m['id'] ?? '?', $m['downloads'] ?? '?', $m['likes'] ?? '?'));
            }
        }

        // берём реальные топ-модели из списка HF, а не хардкод (старый hf-inference их не поддерживает)
        $candidates = array_column(array_slice($models ?? [], 0, 5), 'id');
        if (empty($candidates)) $candidates = [
            'Qwen/Qwen2.5-7B-Instruct',
            'google/gemma-2-9b-it',
        ];
        if ($this->model) $candidates = [$this->model];

        $this->stdout("\nТестирую " . count($candidates) . " HF моделей (wait_for_model=true)...\n\n", Console::FG_YELLOW);
        $messages = [
            ['role' => 'system', 'content' => 'Верни ТОЛЬКО валидный JSON: {"ok":true,"text":"привет"}'],
            ['role' => 'user', 'content' => 'Верни JSON {"ok":true,"text":"тест скорости"}'],
        ];

        foreach ($candidates as $idx => $mid) {
            $this->stdout(sprintf("[%d/%d] %-45s ... ", $idx + 1, count($candidates), $mid), Console::FG_CYAN);
            @flush();
            $t0 = microtime(true);
            $resp = $hf->chat($messages, $mid, null, 0.3);
            $dt = microtime(true) - $t0;
            if ($resp && isset($resp['content'])) {
                $j = json_decode(trim($resp['content']), true);
                if (!is_array($j) && preg_match('/\{.*\}/s', $resp['content'], $mm)) $j = json_decode($mm[0], true);
                $ok = is_array($j) && isset($j['ok']);
                $this->stdout(sprintf("%s %.2fs %s\n", $ok ? "OK" : "NO_JSON", $dt, mb_substr($resp['content'], 0, 60)), $ok ? Console::FG_GREEN : Console::FG_RED);
            } else {
                $this->stdout(sprintf("FAIL %.2fs %s\n", $dt, mb_substr($hf->lastError ?? '', 0, 80)), Console::FG_RED);
            }
            if ($idx < count($candidates) - 1) sleep(1);
        }
        return ExitCode::OK;
    }

    /**
     * Комбайн: bench + check
     *  php yii bench/all --limit=6
     */
    public function actionAll()
    {
        $this->stdout("=== 1/2 bench/free ===\n", Console::FG_CYAN);
        $ret = $this->actionFree();
        $this->stdout("\n=== 2/2 bench/check ===\n", Console::FG_CYAN);
        $ret2 = $this->actionCheck();
        return ($ret === ExitCode::OK && $ret2 === ExitCode::OK) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function isSeoSuitable(string $id): bool
    {
        $low = strtolower($id);
        $bad = ['code','lyria','clip','audio','whisper','tts','vision','image','embedding','rerank','transcribe'];
        foreach ($bad as $b) if (str_contains($low, $b)) return false;
        if (str_contains($low, 'reasoning')) return false;
        return true;
    }

    private function qualityPriority(string $id, float $time, $ctx = null): int
    {
        $low = strtolower($id);
        // до 5с — качество > скорость; ctx 512k лучше 262k > 65k
        $ctxBonus = 0;
        if ($ctx) {
            $ctx = (int)$ctx;
            if ($ctx >= 500000) $ctxBonus = -3;
            elseif ($ctx >= 200000) $ctxBonus = 0;
            else $ctxBonus = 5;
        }
        if (str_contains($low, 'pro')) return 10 + $ctxBonus;
        if (str_contains($low, 'dots')) return 12 + $ctxBonus; // 512k — плюс за контекст
        if (str_contains($low, 'gemma-4-31b')) return 13 + $ctxBonus;
        if (str_contains($low, 'gemma-4-26b')) return 14 + $ctxBonus;
        if (str_contains($low, 'nemotron-3.5-lightning')) return 15 + $ctxBonus;
        if (str_contains($low, 'nemotron-3-super')) return 16 + $ctxBonus;
        if (str_contains($low, 'qwen')) return 17 + $ctxBonus;
        if (str_contains($low, 'llama')) return 18 + $ctxBonus;
        if (str_contains($low, 'sante') || str_contains($low, 'fin')) return 25;
        if (str_contains($low, 'mini') && !str_contains($low, 'pro')) return 28;
        if (str_contains($low, 'lfm')) return 40 + $ctxBonus;
        return 20 + $ctxBonus;
    }

    private function seoHint(string $id): string
    {
        $low = strtolower($id);
        if (str_contains($low, 'gemma')) return 'хорош для JSON, пробуй сводку';
        if (str_contains($low, 'nemotron')) return 'быстрый, пробуй сводку';
        if (str_contains($low, 'qwen') || str_contains($low, 'llama')) return 'подходит для SEO';
        if (str_contains($low, 'ling')) return 'reasoning, может падать length';
        if (str_contains($low, 'lfm') || str_contains($low, 'dots')) return 'маленькая, слабее';
        return 'тест `bench/free --model='.$id.'`';
    }

    private function askModelAboutSeo(string $modelId): ?string
    {
        try {
            $or = Yii::createObject(\app\components\OpenRouterClient::class);
            $or->timeout = 20;
            $or->maxRetries = 0;
            $msgs = [
                ['role' => 'system', 'content' => 'Ответь кратко 2-3 предложения на русском.'],
                ['role' => 'user', 'content' => 'Кратко расскажи про себя: кто ты, для чего создан, подходишь ли для генерации SEO заголовков (до 60 симв) и описаний (1500-4000 симв) для Wildberries в JSON?'],
            ];
            $resp = $or->chat($msgs, $modelId, null, 0.4);
            if ($resp && !empty($resp['content'])) {
                $txt = trim($resp['content']);
                $txt = preg_replace('/\s+/', ' ', $txt);
                return $txt;
            }
        } catch (\Throwable $e) {}
        return null;
    }

    private function sendTelegram(string $text, $replyMarkup = null): void
    {
        $token = Yii::$app->params['telegramBotToken'] ?? '';
        $chat = Yii::$app->params['telegramChatId'] ?? '';
        if (!$token || !$chat) {
            $this->stdout("  (Telegram не настроен: задай telegramBotToken/telegramChatId в params.php)\n", Console::FG_RED);
            return;
        }
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $data = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
            if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
            $resp = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://api.telegram.org/bot{$token}/sendMessage")
                ->setData($data)
                ->setOptions(['timeout' => 10])
                ->send();
            if ($resp->isOk) $this->stdout("  → Telegram отправлен\n", Console::FG_GREEN);
            else {
                $this->stderr("  Telegram fail: {$resp->content}\n", Console::FG_RED);
                // ретрай plain без HTML
                $plain = strip_tags(str_replace(['<code>','</code>','<b>','</b>'], '', $text));
                $resp2 = $client->createRequest()->setMethod('POST')->setUrl("https://api.telegram.org/bot{$token}/sendMessage")->setData(['chat_id'=>$chat,'text'=>$plain])->setOptions(['timeout'=>10])->send();
                if ($resp2->isOk) $this->stdout("  → Telegram отправлен (retry plain)\n", Console::FG_GREEN);
            }
        } catch (\Throwable $e) {
            $this->stderr("  Telegram exception: " . $e->getMessage() . "\n", Console::FG_RED);
        }
    }
}
