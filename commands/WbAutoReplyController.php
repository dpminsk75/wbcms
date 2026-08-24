<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;
use app\models\WbReplyRule;
use app\models\WbReplyTemplatePart;

/**
 * Контроллер для автоматических ответов на отзывы без текста (только оценка).
 * Обрабатывает за один запуск ВСЕ подходящие отзывы (лимита нет).
 *
 * Пример запуска:
 * php yii wb-auto-reply/process
 */
class WbAutoReplyController extends Controller
{
    public $force = false; // Пропустить проверку "уже есть ответ" (--force)

    public function options($actionID)
    {
        if ($actionID === 'reply') {
            return ['force'];
        }
        return [];
    }

    public function optionAliases()
    {
        return ['f' => 'force'];
    }

    public function actionProcess()
    {
        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (empty($companies)) {
            $this->stderr("Ошибка: Нет активных компаний.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Отзывы за сегодня. WB API принимает dateFrom/dateTo как unix timestamp.
        $startTimestamp = strtotime(date('Y-m-d') . ' 00:00:00');
        $endTimestamp = strtotime(date('Y-m-d') . ' 23:59:59');

        $this->stdout("=== ЗАПУСК АВТООТВЕТЧИКА ===\n", Console::FG_CYAN);

        foreach ($companies as $company) {
            $token = $company['api_key'] ?? null;
            if (!$token) {
                continue;
            }

            $this->stdout("\nКомпания: {$company['name']}\n", Console::FG_YELLOW);

            // 1. Синхронизируем ВСЕ отзывы за сегодня (и отвеченные, и неотвеченные).
            //    Это важно: если кто-то ответил на отзыв вручную (не через наш скрипт),
            //    поле answer в БД должно это отразить, иначе мы попытаемся ответить повторно.
            $this->stdout("Синхронизация отзывов за сегодня...\n", Console::FG_GREY);
            $this->syncTodayFeedbacks($company['id'], $token, $startTimestamp, $endTimestamp);

            // 2. Ищем отзывы, подходящие под условия:
            //    - есть оценка (productValuation > 0)
            //    - нет текста / плюсов / минусов
            //    - нет ответа вообще (answer пуст) — то есть НИКТО ещё не отвечал
            //    - is_auto_replied = 0 — на всякий случай, если answer почему-то не синхронизировался
            $feedbacksToProcess = (new \yii\db\Query())
                ->select(['f.*', 'card_brand' => 'c.brand'])
                ->from(['f' => 'wb_feedbacks'])
                ->leftJoin(['c' => 'wbcards'], 'c.nmID = f.nmID')
                ->where(['f.company_id' => $company['id']])
                ->andWhere(['f.is_auto_replied' => 0])
                ->andWhere(['>', 'f.productValuation', 0])
                ->andWhere(['or', ['f.text' => null], ['f.text' => '']])
                ->andWhere(['or', ['f.pros' => null], ['f.pros' => '']])
                ->andWhere(['or', ['f.cons' => null], ['f.cons' => '']])
                ->andWhere(['or', ['f.answer' => null], ['f.answer' => ''], ['f.answer' => 'null']])
                ->orderBy(['f.createdDate' => SORT_ASC])
                ->all();

            $count = count($feedbacksToProcess);
            if ($count === 0) {
                $this->stdout("Нет подходящих отзывов для ответа.\n", Console::FG_GREEN);
                continue;
            }

            $ids = array_column($feedbacksToProcess, 'id');

            $this->stdout("Отзывы без ответа (найдено: {$count}): " . implode(', ', $ids) . "\n\n", Console::FG_CYAN);

            // 3. Цикл по отзывам и генерация ответа
            foreach ($feedbacksToProcess as $fb) {
                $this->stdout("Обработка отзыва ID: {$fb['id']} (nmID: {$fb['nmID']})\n", Console::FG_YELLOW);

                $debugParts = [];
                $replyText = $this->generateReplyText($fb, $debugParts);

                if (empty($replyText)) {
                    $hasTextInfo = (trim((string)($fb['text'] ?? '')) !== '') ? 'есть' : 'нет';
                    $hasProsInfo = (trim((string)($fb['pros'] ?? '')) !== '') ? 'есть' : 'нет';
                    $hasConsInfo = (trim((string)($fb['cons'] ?? '')) !== '') ? 'есть' : 'нет';
                    $this->stdout(
                        "-> Пропуск ID {$fb['id']} (nmID: {$fb['nmID']}): не удалось подобрать правило для ответа " .
                        "(рейтинг: {$fb['productValuation']}, текст: {$hasTextInfo}, плюсы: {$hasProsInfo}, минусы: {$hasConsInfo}).\n\n",
                        Console::FG_RED
                    );
                    continue;
                }

                $this->stdout(
                    "Правило: {$debugParts['rule_id']} | greeting_id: " . ($debugParts['greeting_id'] ?? '—') .
                    " | body_id: " . ($debugParts['body_id'] ?? '—') .
                    " | signoff_id: " . ($debugParts['signoff_id'] ?? '—') . "\n",
                    Console::FG_GREY
                );

                $this->stdout("Сгенерированный ответ:\n{$replyText}\n", Console::FG_GREEN);

                // 4. Отправляем ответ по API
                $this->stdout("Отправка ответа в WB API...\n", Console::FG_GREY);
                $sendResult = $this->sendAnswerToWb($token, $fb['id'], $replyText, $company['id']);

                if ($sendResult['success']) {
                    $this->stdout("Ожидание 3 секунды...\n", Console::FG_GREY);
                    sleep(3);

                    // 5. Проверяем публикацию
                    $this->stdout("Проверка публикации ответа...\n", Console::FG_GREY);
                    $publishedAnswer = $this->checkPublishedAnswer($token, $fb['id'], $company['id']);

                    if ($publishedAnswer) {
                        // 6. Сохраняем в БД и явно помечаем, что ответили именно МЫ
                        Yii::$app->db->createCommand()->update('wb_feedbacks', [
                            'answer' => Json::encode(['text' => $publishedAnswer, 'state' => 'published']),
                            'is_auto_replied' => 1,
                            'rule_id' => $debugParts['rule_id'] ?? null,
                            'updated_at' => time(),
                        ], ['id' => $fb['id']])->execute();

                        $this->stdout("[SUCCESS] Ответ успешно опубликован и сохранен в БД (feedback ID {$fb['id']}, nmID {$fb['nmID']}).\n\n", Console::FG_GREEN);
                    } else {
                        $this->stderr("[ERROR] Ответ отправлен, но при проверке не найден в WB API (feedback ID {$fb['id']}).\n\n", Console::FG_RED);
                    }
                } else {
                    $this->stderr("[ERROR] Ошибка при отправке ответа в WB API (feedback ID {$fb['id']}). HTTP: {$sendResult['httpCode']}. Ответ сервера: {$sendResult['body']}\nТекст, который пытались отправить:\n{$replyText}\n\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("=== ЗАВЕРШЕНО ===\n", Console::FG_CYAN);
        return ExitCode::OK;
    }

    /**
     * Ручной ответ на конкретный отзыв по ID (по тем же правилам, что и авто-обработка).
     *
     * Пример запуска:
     * php yii wb-auto-reply/reply jB4SVlEFyWfyyvKhTvst
     *
     * С флагом --force (или -f) отправит ответ, даже если в БД у отзыва уже есть answer
     * (по умолчанию команда откажется отвечать повторно, чтобы не задвоить ответ).
     */
    public function actionReply($feedbackId)
    {
        $feedbackId = trim($feedbackId);
        if ($feedbackId === '') {
            $this->stderr("Использование: php yii wb-auto-reply/reply <feedbackId>\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $fb = (new \yii\db\Query())
            ->select(['f.*', 'card_brand' => 'c.brand'])
            ->from(['f' => 'wb_feedbacks'])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = f.nmID')
            ->where(['f.id' => $feedbackId])
            ->one();

        if (!$fb) {
            $this->stderr("Отзыв с ID '{$feedbackId}' не найден в БД. Возможно, его ещё не синхронизировали — запустите wb-feedbacks/sync.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $company = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['id' => $fb['company_id'], 'is_active' => 1])
            ->one();

        if (!$company || empty($company['api_key'])) {
            $this->stderr("Компания для отзыва (company_id={$fb['company_id']}) не найдена, неактивна или без токена api_key.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $token = $company['api_key'];

        $this->stdout("Отзыв ID: {$fb['id']} | nmID: {$fb['nmID']} | Компания: {$company['name']} | Оценка: {$fb['productValuation']}\n", Console::FG_YELLOW);

        $existingAnswer = trim((string)($fb['answer'] ?? ''));
        $hasExistingAnswer = ($existingAnswer !== '' && $existingAnswer !== 'null');

        if ($hasExistingAnswer && !$this->force) {
            $this->stderr("У отзыва уже есть ответ в БД (is_auto_replied={$fb['is_auto_replied']}). Чтобы отправить повторно, добавьте флаг --force.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $debugParts = [];
        $replyText = $this->generateReplyText($fb, $debugParts);

        if (empty($replyText)) {
            $hasTextInfo = (trim((string)($fb['text'] ?? '')) !== '') ? 'есть' : 'нет';
            $hasProsInfo = (trim((string)($fb['pros'] ?? '')) !== '') ? 'есть' : 'нет';
            $hasConsInfo = (trim((string)($fb['cons'] ?? '')) !== '') ? 'есть' : 'нет';
            $this->stderr(
                "Не удалось подобрать правило для ответа (рейтинг: {$fb['productValuation']}, " .
                "текст: {$hasTextInfo}, плюсы: {$hasProsInfo}, минусы: {$hasConsInfo}).\n",
                Console::FG_RED
            );
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(
            "Правило: {$debugParts['rule_id']} | greeting_id: " . ($debugParts['greeting_id'] ?? '—') .
            " | body_id: " . ($debugParts['body_id'] ?? '—') .
            " | signoff_id: " . ($debugParts['signoff_id'] ?? '—') . "\n",
            Console::FG_GREY
        );

        $this->stdout("Сгенерированный ответ:\n{$replyText}\n", Console::FG_GREEN);

        $this->stdout("Отправка ответа в WB API...\n", Console::FG_GREY);
        $sendResult = $this->sendAnswerToWb($token, $fb['id'], $replyText, $company['id']);

        if (!$sendResult['success']) {
            $this->stderr("[ERROR] Ошибка при отправке ответа в WB API. HTTP: {$sendResult['httpCode']}. Ответ сервера: {$sendResult['body']}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Ожидание 3 секунды...\n", Console::FG_GREY);
        sleep(3);

        $this->stdout("Проверка публикации ответа...\n", Console::FG_GREY);
        $publishedAnswer = $this->checkPublishedAnswer($token, $fb['id'], $company['id']);

        if (!$publishedAnswer) {
            $this->stderr("[ERROR] Ответ отправлен, но при проверке не найден в WB API (feedback ID {$fb['id']}).\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        Yii::$app->db->createCommand()->update('wb_feedbacks', [
            'answer' => Json::encode(['text' => $publishedAnswer, 'state' => 'published']),
            'is_auto_replied' => 1,
            'rule_id' => $debugParts['rule_id'] ?? null,
            'updated_at' => time(),
        ], ['id' => $fb['id']])->execute();

        $this->stdout("[SUCCESS] Ответ успешно опубликован и сохранен в БД (feedback ID {$fb['id']}, nmID {$fb['nmID']}).\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Генерация текста по правилам (та же логика, что в WbReplyRulesController::actionTestGeneration).
     * hasText вычисляется по реальному содержимому отзыва — так функцию можно использовать
     * и для авто-обработки (где текста гарантированно нет), и для ручного вызова по ID
     * (где отзыв может быть с текстом).
     */
    private function generateReplyText($fb, &$debugPartIds = [])
    {
        $rules = WbReplyRule::find()->where(['is_active' => 1])->all();

        $fbRating = (int)($fb['productValuation'] ?? 5);
        $fbNmId = $fb['nmID'] ?? null;
        $fbBrand = $fb['card_brand'] ?? null;

        $fbText = trim((string)($fb['text'] ?? ''));
        $hasText = !empty($fbText);

        $bestRules = [];
        foreach ($rules as $rule) {
            if ($fbRating < $rule->rating_min || $fbRating > $rule->rating_max) {
                continue;
            }
            if ($rule->text_condition === 'with_text' && !$hasText) {
                continue;
            }
            if ($rule->text_condition === 'no_text' && $hasText) {
                continue;
            }

            if ($rule->rule_type === 'product') {
                $isSaved = (new \yii\db\Query())->from('wb_reply_rule_products')->where(['rule_id' => $rule->id, 'nmID' => $fbNmId])->exists();
                if ($isSaved) $bestRules[1][] = $rule;
            } elseif ($rule->rule_type === 'brand') {
                if (!empty($fbBrand)) {
                    $isSaved = (new \yii\db\Query())->from('wb_reply_rule_brands')->where(['rule_id' => $rule->id, 'brand_name' => $fbBrand])->exists();
                    if ($isSaved) $bestRules[2][] = $rule;
                }
            } else {
                $bestRules[3][] = $rule;
            }
        }

        $matchedRule = $bestRules[1][0] ?? $bestRules[2][0] ?? $bestRules[3][0] ?? null;

        if (!$matchedRule) {
            return null;
        }

        $greetingsById = \yii\helpers\ArrayHelper::map(WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_GREETING])->all(), 'id', 'text');
        $bodiesById = \yii\helpers\ArrayHelper::map(WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_BODY])->all(), 'id', 'text');
        $signoffsById = \yii\helpers\ArrayHelper::map(WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_SIGNOFF])->all(), 'id', 'text');

        $parts = [];
        $userName = trim($fb['userName'] ?? '');
        $hasName = !empty($userName);
        $debugPartIds = ['rule_id' => $matchedRule->id, 'greeting_id' => null, 'body_id' => null, 'signoff_id' => null];

        if (!empty($greetingsById)) {
            $fallbackGreetings = []; // id => text (без спецтега)
            $specialGreetings = [];  // id => text (для случая "без имени")

            foreach ($greetingsById as $gId => $greetText) {
                if (stripos($greetText, '{{без_имени}}') !== false) {
                    $specialGreetings[$gId] = preg_replace('/{{без_имени}}/ui', '', $greetText);
                } else {
                    $fallbackGreetings[$gId] = $greetText;
                }
            }

            if (!$hasName && !empty($specialGreetings)) {
                $pickId = array_rand($specialGreetings);
                $parts[] = $specialGreetings[$pickId];
                $debugPartIds['greeting_id'] = $pickId;
            } elseif (!empty($fallbackGreetings)) {
                $pickId = array_rand($fallbackGreetings);
                $parts[] = $fallbackGreetings[$pickId];
                $debugPartIds['greeting_id'] = $pickId;
            }
        }

        if (!empty($bodiesById)) {
            $pickId = array_rand($bodiesById);
            $parts[] = $bodiesById[$pickId];
            $debugPartIds['body_id'] = $pickId;
        }
        if (!empty($signoffsById)) {
            $pickId = array_rand($signoffsById);
            $parts[] = $signoffsById[$pickId];
            $debugPartIds['signoff_id'] = $pickId;
        }

        $separator = ($matchedRule->part_separator === 'newline') ? "\n" : (($matchedRule->part_separator === 'paragraph') ? "\n\n" : " ");
        $generatedText = implode($separator, array_filter($parts));

        if ($hasName) {
            $generatedText = str_ireplace('{{имя}}', $userName, $generatedText);
        } else {
            $generatedText = str_ireplace('{{имя}}', '', $generatedText);
            $generatedText = str_replace('  ', ' ', $generatedText);
        }

        return trim($generatedText);
    }

    /**
     * Отправка ответа в WB API.
     * Возвращает ['success' => bool, 'httpCode' => int, 'body' => string] — тело ответа
     * нужно для диагностики, если WB вернёт ошибку в JSON при httpCode 200/4xx.
     */
    private function sendAnswerToWb($token, $feedbackId, $text, $companyId = null)
    {
        $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks/answer';
        $payload = [
            'id' => $feedbackId,
            'text' => $text,
        ];

        $response = Yii::$app->wbHttpClient->post($url, $payload, $token, $companyId);
        $httpCode = (int)$response->getStatusCode();
        $content = $response->content;

        return [
            'success' => in_array($httpCode, [200, 204], true),
            'httpCode' => $httpCode,
            'body' => $content,
        ];
    }

    /**
     * Проверка публикации ответа через GET /api/v1/feedback
     */
    private function checkPublishedAnswer($token, $feedbackId, $companyId = null)
    {
        $url = "https://feedbacks-api.wildberries.ru/api/v1/feedback?id={$feedbackId}";

        $response = Yii::$app->wbHttpClient->get($url, [], $token, $companyId);
        $httpCode = (int)$response->getStatusCode();
        $content = $response->content;
        $data = $response->data;
        if ($data === null && $content) {
            try {
                $data = Json::decode($content);
            } catch (\Throwable $e) {
                $data = null;
            }
        }

        if ($httpCode === 200 && $data) {
            if (isset($data['data']['answer']['text']) && trim($data['data']['answer']['text']) !== '') {
                return $data['data']['answer']['text'];
            }
        }
        return false;
    }

    /**
     * Синхронизация отзывов за сегодня — И отвеченных, И неотвеченных.
     * Синхронизируем оба статуса, чтобы поле answer в БД всегда отражало
     * реальное состояние на WB (в том числе если кто-то ответил вручную,
     * не через наш автоответчик) — иначе мы рискуем ответить повторно.
     */
    private function syncTodayFeedbacks($companyId, $token, $dateFrom, $dateTo)
    {
        $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks';
        $take = 500;

        foreach (['false', 'true'] as $isAnswered) {
            $skip = 0;
            $hasMore = true;

            while ($hasMore) {
                $params = [
                    'isAnswered' => $isAnswered,
                    'take' => $take,
                    'skip' => $skip,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ];

                $fullUrl = $url . '?' . http_build_query($params);
                $response = Yii::$app->wbHttpClient->get($fullUrl, [], $token, $companyId);
                $httpCode = (int)$response->getStatusCode();
                $content = $response->content;
                $data = $response->data;
                if ($data === null && $content) {
                    try {
                        $data = Json::decode($content);
                    } catch (\Throwable $e) {
                        $data = null;
                    }
                }

                if ($httpCode !== 200 || !$data) {
                    $this->stderr("Ошибка синхронизации (isAnswered={$isAnswered}, skip={$skip}). HTTP: {$httpCode}\n", Console::FG_RED);
                    break;
                }
                $feedbacks = $data['data']['feedbacks'] ?? [];
                $count = count($feedbacks);

                if ($count === 0) {
                    break;
                }

                foreach ($feedbacks as $item) {
                    $id = $item['id'] ?? null;
                    if (!$id) continue;

                    $isPay = isset($item['isPay']) ? ($item['isPay'] ? 1 : 0) : 0;
                    $fCost = $item['fCost'] ?? $item['bableCost'] ?? null;

                    Yii::$app->db->createCommand()->upsert('wb_feedbacks', [
                        'id' => $id,
                        'company_id' => $companyId,
                        'imtId' => $item['imtId'] ?? 0,
                        'nmID' => $item['productDetails']['nmId'] ?? 0,
                        'subjectId' => $item['subjectId'] ?? null,
                        'subjectName' => $item['subjectName'] ?? null,
                        'userName' => $item['userName'] ?? null,
                        'matchingSize' => $item['matchingSize'] ?? null,
                        'color' => $item['color'] ?? null,
                        'text' => $item['text'] ?? null,
                        'pros' => $item['pros'] ?? null,
                        'cons' => $item['cons'] ?? null,
                        'productValuation' => $item['productValuation'] ?? 0,
                        'isNew' => isset($item['isNew']) ? ($item['isNew'] ? 1 : 0) : 1,
                        'state' => $item['state'] ?? null,
                        'status' => $item['status'] ?? null,
                        'orderStatus' => $item['orderStatus'] ?? null,
                        'is_pay' => $isPay,
                        'f_cost' => $fCost,
                        'is_archive' => 0, // Синхронизируем свежие актуальные
                        'createdDate' => isset($item['createdDate']) ? date('Y-m-d H:i:s', strtotime($item['createdDate'])) : date('Y-m-d H:i:s'),
                        'updatedDate' => isset($item['updatedDate']) ? date('Y-m-d H:i:s', strtotime($item['updatedDate'])) : null,

                        'productDetails' => Json::encode($item['productDetails'] ?? []),
                        'photoLinks' => isset($item['photoLinks']) ? Json::encode($item['photoLinks']) : null,
                        'video' => isset($item['video']) ? Json::encode($item['video']) : null,
                        'answer' => isset($item['answer']) ? Json::encode($item['answer']) : null,
                        'bables' => isset($item['bables']) ? Json::encode($item['bables']) : null,

                        'created_at' => time(),
                        'updated_at' => time(),
                    ], [
                        // При обновлении трогаем только то, что могло измениться.
                        // answer обновляем ВСЕГДА — именно так мы узнаём, что кто-то
                        // ответил на отзыв не через наш автоответчик.
                        'text' => $item['text'] ?? null,
                        'pros' => $item['pros'] ?? null,
                        'cons' => $item['cons'] ?? null,
                        'productValuation' => $item['productValuation'] ?? 0,
                        'answer' => isset($item['answer']) ? Json::encode($item['answer']) : null,
                        'updated_at' => time(),
                    ])->execute();
                }

                if ($count < $take) {
                    $hasMore = false;
                } else {
                    $skip += $take;
                }
            }
        }
    }
}
