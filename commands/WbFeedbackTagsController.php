<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * Сканирует поле bables во всех отзывах, собирает уникальные теги
 * и наполняет/обновляет справочник wb_feedback_tags (usage_count).
 * Новые теги попадают туда со sentiment='neutral' — разметка руками в админке.
 *
 * Запуск:
 * php yii wb-feedback-tags/sync
 */
class WbFeedbackTagsController extends Controller
{
    public function actionSync()
    {
        $this->stdout("Сканирование поля bables в wb_feedbacks...\n", Console::FG_CYAN);

        // Считаем частоту каждого тега по всем отзывам, где bables не пусто
        $rows = (new \yii\db\Query())
            ->select(['bables'])
            ->from('wb_feedbacks')
            ->where(['not', ['bables' => null]])
            ->andWhere(['not in', 'bables', ['', 'null', '[]']])
            ->each(); // построчно, чтобы не тащить всё в память разом

        $counts = [];
        $totalRows = 0;

        foreach ($rows as $row) {
            $totalRows++;
            $raw = trim((string)$row['bables']);
            if ($raw === '') {
                continue;
            }

            try {
                $decoded = Json::decode($raw);
            } catch (\Throwable $e) {
                $this->stderr("Пропуск bables (битый JSON): " . mb_substr($raw, 0, 80) . " — " . $e->getMessage() . "\n", Console::FG_YELLOW);
                continue;
            }
            // На случай двойного кодирования (строка внутри строки)
            $guard = 0;
            while (is_string($decoded) && $guard < 3) {
                try {
                    $decoded = Json::decode($decoded);
                } catch (\Throwable $e) {
                    $this->stderr("Пропуск bables (битый вложенный JSON): " . $e->getMessage() . "\n", Console::FG_YELLOW);
                    $decoded = null;
                    break;
                }
                $guard++;
            }

            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') {
                    continue;
                }
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        $this->stdout("Обработано строк: {$totalRows}. Найдено уникальных тегов: " . count($counts) . "\n", Console::FG_CYAN);

        $now = time();
        $newTags = 0;
        $updatedTags = 0;

        foreach ($counts as $tagText => $usageCount) {
            $existing = (new \yii\db\Query())
                ->select(['id', 'sentiment'])
                ->from('wb_feedback_tags')
                ->where(['tag_text' => $tagText])
                ->one();

            if ($existing) {
                Yii::$app->db->createCommand()->update('wb_feedback_tags', [
                    'usage_count' => $usageCount,
                    'updated_at' => $now,
                ], ['id' => $existing['id']])->execute();
                $updatedTags++;
            } else {
                Yii::$app->db->createCommand()->insert('wb_feedback_tags', [
                    'tag_text' => $tagText,
                    'sentiment' => 'neutral',
                    'usage_count' => $usageCount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
                $newTags++;
            }
        }

        $this->stdout("[SUCCESS] Новых тегов: {$newTags}. Обновлено (usage_count): {$updatedTags}.\n", Console::FG_GREEN);
        $this->stdout("Разметить новые теги можно в админке: /wb-feedback-tags/index\n", Console::FG_CYAN);

        return ExitCode::OK;
    }
}
