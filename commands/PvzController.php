<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class PvzController extends Controller
{
    /**
     * Импорт ПВЗ с учетом стран и вложенности JSON
     * php yii pvz/import path/to/file.json pvz_2026_12
     */
    public function actionImport($filePath, $tableName = 'pvz_2026') 
    {
        $fullPath = Yii::getAlias('@app/' . $filePath);

        if (!file_exists($fullPath)) {
            $this->stderr("Ошибка: Файл не найден: $fullPath\n", Console::FG_RED);
            return ExitCode::IOERR;
        }

        $this->createTableIfNotExists($tableName);

        $this->stdout("Чтение и декодирование JSON...\n");
        $jsonContent = file_get_contents($fullPath);
        $data = json_decode($jsonContent, true);

        if (!is_array($data)) {
            $this->stderr("Ошибка: Некорректный формат JSON\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $batch = [];
        $batchSize = 1000;
        $inserted = 0;
        $columns = ['country', 'wb_id', 'address', 'work_time', 'lat', 'lon', 'dtype', 'is_wb', 'pickup_type', 'raw_data'];

        foreach ($data as $countryGroup) {
            $countryCode = $countryGroup['country'] ?? 'ru';
            $items = $countryGroup['items'] ?? [];
            $totalInGroup = count($items);
            
            $this->stdout("Обработка страны [$countryCode]: $totalInGroup записей\n", Console::FG_CYAN);
            Console::startProgress(0, $totalInGroup, "Загрузка $countryCode: ");

            foreach ($items as $index => $item) {
                $batch[] = [
                    $countryCode,
                    $item['id'],
                    $item['address'] ?? null,
                    $item['workTime'] ?? null,
                    $item['coordinates'][0] ?? null,
                    $item['coordinates'][1] ?? null,
                    $item['dtype'] ?? 0,
                    isset($item['isWb']) ? (int)$item['isWb'] : 0,
                    $item['pickupType'] ?? 0,
                    json_encode($item, JSON_UNESCAPED_UNICODE)
                ];

                if (count($batch) >= $batchSize) {
                    $inserted += Yii::$app->db->createCommand()
                        ->batchInsert($tableName, $columns, $batch)
                        ->execute();
                    $batch = [];
                }
                Console::updateProgress($index + 1, $totalInGroup);
            }
            Console::endProgress();
        }

        if (!empty($batch)) {
            $inserted += Yii::$app->db->createCommand()
                ->batchInsert($tableName, $columns, $batch)
                ->execute();
        }

        $this->stdout("\nГотово! Всего импортировано: $inserted\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Объединение таблиц в pvz_2026_99
     */
    public function actionMerge()
    {
        $db = Yii::$app->db;
        $targetTable = 'pvz_2026_99';
        $months = ['12', '11', '10', '09', '08', '07', '06', '05', '04', '03', '02'];
        
        // Список колонок для переноса (id исключаем)
        $cols = '`country`, `wb_id`, `address`, `work_time`, `lat`, `lon`, `dtype`, `is_wb`, `pickup_type`, `raw_data`';

        $this->stdout("Создание сводной таблицы $targetTable...\n");
        $db->createCommand("DROP TABLE IF EXISTS `$targetTable`")->execute();
        $this->createTableIfNotExists($targetTable);

        foreach ($months as $month) {
            $sourceTable = "pvz_2026_$month";
            if (!$db->createCommand("SHOW TABLES LIKE '$sourceTable'")->queryScalar()) continue;

            $this->stdout("Слияние $sourceTable... ");
            
            if ($month === '12') {
                $sql = "INSERT INTO `$targetTable` ($cols) SELECT $cols FROM `$sourceTable`";
            } else {
                // Догружаем только те wb_id, которых еще нет в итоговой таблице
                $sql = "INSERT INTO `$targetTable` ($cols) 
                        SELECT $cols FROM `$sourceTable` AS src
                        WHERE NOT EXISTS (SELECT 1 FROM `$targetTable` AS tgt WHERE tgt.wb_id = src.wb_id)";
            }

            $count = $db->createCommand($sql)->execute();
            $this->stdout("добавлено $count новых ПВЗ\n", Console::FG_GREEN);
        }
    }

    private function createTableIfNotExists($tableName)
    {
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `country` VARCHAR(3) DEFAULT 'ru',
            `wb_id` INT DEFAULT NULL,
            `address` VARCHAR(500) NULL,
            `work_time` TEXT NULL, 
            `lat` DECIMAL(10,8) NULL,
            `lon` DECIMAL(11,8) NULL,
            `dtype` TINYINT DEFAULT '0',
            `is_wb` TINYINT(1) DEFAULT '0',
            `pickup_type` TINYINT DEFAULT '0',
            `raw_data` JSON DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_wb_id` (`wb_id`),
            KEY `idx_country` (`country`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

        Yii::$app->db->createCommand($sql)->execute();
    }
}