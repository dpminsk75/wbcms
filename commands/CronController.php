<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class CronController extends Controller
{
    /**
     * Главная команда для запуска всех синхронизаций.
     * Вызывается как: php yii cron/sync-all
     */
    public function actionSyncAll()
    {
        $this->stdout("--- Старт общей синхронизации " . date('Y-m-d H:i:s') . " ---\n");

        // Список команд, которые нужно запустить по очереди
        $commands = [
            'wb/sync-cards',
            'wb-stock/sync',
            'wb-stock-offices/sync',
            'wb-product-analytics/sync',
            'wb-paid-storage/sync',
            'wb-acceptance-report/sync',
        ];

        foreach ($commands as $route) {
            $this->stdout("Запуск: php yii {$route}\n");

            try {
                // Внутренний вызов консольного экшена Yii2
                $exitCode = Yii::$app->runAction($route);
                if ((int)$exitCode === ExitCode::OK) {
                    $this->stdout("Завершено успешно (код возврата: " . (int)$exitCode . "): php yii {$route}\n\n");
                } else {
                    $this->stderr("Завершено с ошибкой (код возврата: " . (int)$exitCode . "): php yii {$route}\n\n");
                }
            } catch (\Throwable $e) {
                $this->stderr("Ошибка при выполнении {$route}: " . get_class($e) . ": " . $e->getMessage() . "\n\n");
            }
        }

        $this->stdout("--- Все синхронизации завершены " . date('Y-m-d H:i:s') . " ---\n");
        return ExitCode::OK;
    }
}
