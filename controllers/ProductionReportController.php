<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use app\models\ProductionPlanItem;
use app\models\Company;
use app\components\ProductionReportService;

class ProductionReportController extends Controller
{
    const STATUS_OK = 'ok';
    const STATUS_WARN = 'warn';
    const STATUS_DANGER = 'danger';
    const STATUS_UNKNOWN = 'unknown';

    public function actionIndex($period = null)
    {
        // period приходит как 'Y-m' из <input type="month">, по умолчанию - текущий месяц
        $period = $period && preg_match('/^\d{4}-\d{2}$/', $period) ? $period : date('Y-m');
        $periodDate = $period . '-01';

        $companyId = null;
        $showCompanyColumn = false;
        if (Yii::$app->has('companyManager')) {
            $showCompanyColumn = Yii::$app->companyManager->isGlobalMode();
            $companyId = $showCompanyColumn ? null : Yii::$app->companyManager->getCurrentId();
        }

        $itemsQuery = ProductionPlanItem::find()
            ->joinWith('wbCard')
            ->orderBy(['{{%production_plan_item}}.id' => SORT_ASC]);

        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($itemsQuery, 'wbcards');
        }

        $items = $itemsQuery->all();

        $companyAbbrMap = $showCompanyColumn ? Company::abbreviationMap() : [];

        if (empty($items)) {
            return $this->render('index', [
                'dataProvider' => new ArrayDataProvider(['allModels' => [], 'pagination' => false]),
                'period' => $period,
                'showCompanyColumn' => $showCompanyColumn,
            ]);
        }

        $nmIds = array_map(fn($i) => $i->nm_id, $items);
        $metrics = ProductionReportService::buildReport($nmIds, $periodDate, $companyId);

        $rows = [];
        foreach ($items as $item) {
            $m = $metrics[$item->nm_id] ?? [
                'smolensk_start' => 0.0, 'smolensk_movements' => 0.0, 'wb_start' => 0.0,
                'orders_since_period' => 0.0, 'orders_last_30' => 0.0,
            ];

            $totalToday = $m['smolensk_start'] + $m['wb_start'] + $m['smolensk_movements'] - $m['orders_since_period'];
            $avgDaily = $m['orders_last_30'] / 30;
            $daysLeft = $avgDaily > 0 ? $totalToday / $avgDaily : null;
            $etaDate = $daysLeft !== null ? date('Y-m-d', strtotime('+' . (int)round($daysLeft) . ' days')) : null;

            $cycleDays = $item->production_days + $item->logistics_smolensk_days + $item->logistics_wb_days;
            $reorderPointDays = $cycleDays + $item->buffer_days;

            $status = self::STATUS_UNKNOWN;
            if ($daysLeft !== null) {
                if ($totalToday <= 0 || $daysLeft <= $reorderPointDays) {
                    $status = self::STATUS_DANGER;
                } elseif ($daysLeft <= $reorderPointDays * 1.3) {
                    $status = self::STATUS_WARN;
                } else {
                    $status = self::STATUS_OK;
                }
            }

            $recommendedQty = max(0, $avgDaily * $item->target_coverage_days - $totalToday);

            $rows[] = [
                'nm_id' => $item->nm_id,
                'vendor_code' => $item->wbCard->vendorCode ?? '?',
                'title' => $item->wbCard->title ?? '(карточка не найдена)',
                'company_label' => $item->wbCard ? ($companyAbbrMap[$item->wbCard->company_id] ?? '—') : '—',
                'smolensk_start' => $m['smolensk_start'],
                'wb_start' => $m['wb_start'],
                'smolensk_movements' => $m['smolensk_movements'],
                'orders_since_period' => $m['orders_since_period'],
                'total_today' => $totalToday,
                'orders_last_30' => $m['orders_last_30'],
                'avg_daily' => $avgDaily,
                'days_left' => $daysLeft,
                'eta_date' => $etaDate,
                'cycle_days' => $cycleDays,
                'reorder_point_days' => $reorderPointDays,
                'status' => $status,
                'recommended_qty' => $recommendedQty,
            ];
        }

        // сортировка по умолчанию: сначала статус ("пора печатать"), внутри статуса - по дате окончания (Закончится)
        $statusOrder = [self::STATUS_DANGER => 0, self::STATUS_WARN => 1, self::STATUS_OK => 2, self::STATUS_UNKNOWN => 3];
        usort($rows, function ($a, $b) use ($statusOrder) {
            // 1. Сравниваем по приоритету статуса
            $statusCompare = $statusOrder[$a['status']] <=> $statusOrder[$b['status']];
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            // 2. Если статусы одинаковые, сравниваем по дате окончания (eta_date)
            $dateA = $a['eta_date'];
            $dateB = $b['eta_date'];

            // Обрабатываем null (если дата не определена, отправляем её в конец группы)
            if ($dateA === null && $dateB === null) {
                return 0;
            }
            if ($dateA === null) {
                return 1;
            }
            if ($dateB === null) {
                return -1;
            }

            // Сортируем по возрастанию: более ранняя дата (срочная) будет выше
            return $dateA <=> $dateB;
        });

        $dataProvider = new ArrayDataProvider([
            'allModels' => $rows,
            'pagination' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'period' => $period,
            'showCompanyColumn' => $showCompanyColumn,
        ]);
    }
}
