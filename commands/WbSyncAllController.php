<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Единая команда для крона: последовательно дергает заказы + FBS-сборки.
 *
 * Было 3 строки:
 *  0,30 * * * * php yii wb-orders/fetch
 *  2,32 * * * * php yii wb-orders/fetch-order-feed
 *  5,35 * * * * php yii wb-orders-fbs/sync
 *
 * Стало 1 строка каждые 5 минут:
 *  каждые 5 минут: /usr/bin/php /var/www/wb/wbcms/yii wb-sync-all > /dev/null 2>&1
 *  или с логом: .../yii wb-sync-all >> /var/log/wb-sync-all.log 2>&1
 */
class WbSyncAllController extends Controller
{
    public $full = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['full']);
    }

    public function actionIndex(): int
    {
        $mode = $this->full ? 'FULL (3 дня)' : 'FAST (1 день/сегодня)';
        $this->stdout("=== wb-sync-all start " . date('Y-m-d H:i:s') . " mode=$mode ===\n", Console::FG_CYAN);
        $code = 0;

        // FAST: тянем только свежие заказы, чтобы прогон каждые 5 мин был быстрым
        // FULL: старый полный проход за 3 дня (для ночного прогона)
        $fetchFrom = $this->full ? null : date('Y-m-d', strtotime('-1 day'));
        $feedFrom  = $this->full ? null : date('Y-m-d', strtotime('-1 day'));
        $feedTo    = $this->full ? null : date('Y-m-d');

        $steps = [
            ['route' => 'wb-orders/fetch', 'params' => $fetchFrom ? [$fetchFrom] : [], 'label' => 'wb-orders/fetch (supplier/orders) from=' . ($fetchFrom ?: 'auto -3d')],
            ['route' => 'wb-orders/fetch-order-feed', 'params' => ($feedFrom ? [$feedFrom, $feedTo] : []), 'label' => 'wb-orders/fetch-order-feed from=' . ($feedFrom ?: 'auto') . ' to=' . ($feedTo ?: 'auto')],
            ['route' => 'wb-orders-fbs/sync', 'params' => [], 'label' => 'wb-orders-fbs/sync (сборки + статусы + вычет виртуал.)'],
        ];

        foreach ($steps as $i => $step) {
            $this->stdout("\n[" . ($i+1) . "/3] {$step['label']} ...\n", Console::FG_YELLOW);
            $res = Yii::$app->runAction($step['route'], $step['params']);
            $exit = is_int($res) ? $res : ExitCode::OK;
            if ($exit !== ExitCode::OK) {
                $this->stderr("  -> {$step['route']} завершился с кодом $exit\n", Console::FG_RED);
                $code = $exit;
            } else {
                $this->stdout("  -> {$step['label']} OK\n", Console::FG_GREEN);
            }
            if ($i < 2) usleep(200000);
        }

        $this->stdout("\n=== wb-sync-all finish " . date('Y-m-d H:i:s') . " code=$code ===\n", Console::FG_CYAN);
        return $code;
    }
}
