<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class CostController extends Controller
{
    /**
     * Импорт себестоимости из CSV файла в таблицу wbcards_costs.
     * * Пример вызова:
     * php yii cost/import @app/runtime/costs.csv
     * php yii cost/import /path/to/costs.csv 2026-06-17
     * * @param string $filePath Путь к файлу CSV
     * @param string|null $date Дата загрузки (ГГГГ-ММ-ДД), если не указана — берется текущая
     */
    public function actionImport($filePath, $date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $realPath = Yii::getAlias($filePath);
        if (!file_exists($realPath)) {
            $this->stdout("Ошибка: Файл не найден по пути '{$realPath}'\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (($handle = fopen($realPath, 'r')) === false) {
            $this->stdout("Ошибка: Не удалось открыть файл '{$realPath}'\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Начало импорта данных в wbcards_costs за дату: {$date}...\n", Console::FG_CYAN);

        // Пропускаем строку заголовков (Товар;Артикул;Бренд;Артикул продавца;Цена)
        fgetcsv($handle, 0, ';');
        
        $processed = 0;
        $transaction = Yii::$app->db->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                // row[1] — Артикул (nmID), row[4] — Цена
                if (empty($row) || !isset($row[1], $row[4])) {
                    continue; 
                }

                $nmID = trim($row[1]);
                $priceRaw = trim($row[4]);

                if (empty($nmID)) {
                    continue;
                }

                // Корректируем формат цены (заменяем запятую на точку для float)
                $price = (float)str_replace(',', '.', $priceRaw);

                // Используем upsert: вставляет строку, либо обновляет цену при совпадении UNIQUE(load_date, nmID)
                Yii::$app->db->createCommand()->upsert(
                    '{{%wbcards_costs}}',
                    [
                        'load_date' => $date,
                        'nmID'      => $nmID,
                        'price'     => $price,
                    ],
                    [
                        'price'     => $price, // Поле для обновления в случае дубликата ключа
                    ]
                )->execute();

                $processed++;
            }

            fclose($handle);
            $transaction->commit();

            $this->stdout("Импорт успешно завершен!\n", Console::FG_GREEN);
            $this->stdout("Обработано позиций (добавлено/обновлено): {$processed}\n", Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Exception $e) {
            $transaction->rollBack();
            fclose($handle);
            $this->stdout("Произошла ошибка во время импорта: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}