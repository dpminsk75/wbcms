<?php
namespace app\controllers;

use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\db\Query;
use Yii;

class WbDetailByPeriodController extends Controller
{
    public function actionIndex()
    {
        $query = (new \yii\db\Query())->from('detail_by_period')->orderBy(['id' => SORT_DESC]);
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }


public function actionWeeklyReport($productId = null, $dateFrom = null, $dateTo = null)
{
    // Устанавливаем даты по умолчанию, если не заданы
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-4 weeks'));
    $dateTo = $dateTo ?: date('Y-m-d');

    // Список товаров для главного фильтра
    $productsData = Yii::$app->db->createCommand(
        "SELECT DISTINCT p.id, p.name FROM product p 
         JOIN product_wb_card pwc ON p.id = pwc.product_id ORDER BY p.name ASC"
    )->queryAll();
    $productsList = \yii\helpers\ArrayHelper::map($productsData, 'id', 'name');

    $data = [];
    $dataNmId = [];
    $groupedNmId = [];
    $allWeeks = [];

    $chartTimelineData = [];
    $chartRegionData = [];
    $chartCountryData = [];
    $relatedCards = [];

    if ($productId) {

        $relatedCards = (new \yii\db\Query())
            ->select(['w.nmId', 'w.title as card_name', 'w.vendorCode as vendorCode'])
            ->from('product_wb_card pwc')
            ->innerJoin('wbcards w', 'pwc.wb_nm_id = w.nmId') // Связь через nmId
            ->where(['pwc.product_id' => $productId])
                ->all();

        $nmIdList = (new \yii\db\Query())
            ->select(['wb_nm_id'])
            ->from('product_wb_card')
            ->where(['product_id' => $productId])
            ->column();

            $repository = new \app\repositories\WeeklyReportRepository();
            $data = $repository->getWeeklyReportByNmid($nmIdList, $dateFrom, $dateTo);


        $dataNmIdP = (new \yii\db\Query())
            ->select([
                'week_key' => 'DATE_FORMAT(sale_dt, "%X-%V")',
                'nm_id' => 'dd.nm_id',
                'vendorCode' => 'cc.vendorCode',
                'title' => 'cc.title',

                'retail_price_full' =>      'SUM(COALESCE(dd.retail_price, 0))'            , 
                'retail_amount_full' =>     'SUM(COALESCE(retail_amount, 0))'           , 
                'ppvz_for_pay_full' =>      'SUM(COALESCE(ppvz_for_pay, 0))'            , 
                'delivery_rub_full' =>      'SUM(COALESCE(delivery_rub, 0))'            , 
                'ppp' => 'pp.p',

                'retail_price' =>           'SUM(COALESCE(dd.retail_price, 0)) * pp.p/100'            , 
                'retail_amount' =>          'SUM(COALESCE(retail_amount, 0)) * pp.p/100'           , 
                'commission_percent' =>     'AVG(COALESCE(commission_percent, 0))'      , 
                'ppvz_spp_prc' =>           'AVG(COALESCE(ppvz_spp_prc, 0))'            , 
                'ppvz_sales_commission' =>  'SUM(COALESCE(ppvz_sales_commission, 0))'   ,  
                'ppvz_reward' =>            'SUM(COALESCE(ppvz_reward, 0)) * pp.p/100'             , 
                'acquiring_fee' =>          'SUM(COALESCE(acquiring_fee, 0)) * pp.p/100'           , 
                'ppvz_vw' =>                'SUM(COALESCE(ppvz_vw, 0)) * pp.p/100'                 , 
                'ppvz_vw_nds' =>            'SUM(COALESCE(ppvz_vw_nds, 0)) * pp.p/100'             , 
                'delivery_rub' =>           'SUM(COALESCE(delivery_rub, 0)) * pp.p/100'            , 
                'rebill_logistic_cost' =>   'SUM(COALESCE(rebill_logistic_cost, 0)) * pp.p/100'    , 
                'ppvz_for_pay' =>           'SUM(COALESCE(ppvz_for_pay, 0)) * pp.p/100'            , 
                'rows_count' =>             'COUNT(*)'                                     ,

                'sales_count' => 'SUM(CASE WHEN doc_type_name = "Продажа" THEN 1 * pp.q ELSE 0 END)',
                'return_count' => 'SUM(CASE WHEN doc_type_name = "Возврат" THEN 1 ELSE 0 END)',
                'retail_sum' => 'SUM(retail_amount)', 
                'for_pay' => 'SUM(ppvz_for_pay)',
                'delivery' => 'SUM(delivery_amount)',
            ])
            ->from(['product_wb_card pp'])
            ->leftJoin('detail_by_period dd', 'pp.wb_nm_id = dd.nm_id')
            ->leftJoin('wbcards cc', 'pp.wb_nm_id = cc.nmID')
            ->where(['pp.product_id' => $productId ]) // $productId
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Продажа'])
            ->groupBy(['week_key', 'nm_id', 'cc.vendorCode', 'cc.title']);

        $dataNmIdL = (new \yii\db\Query())
            ->select([
                'week_key' => 'DATE_FORMAT(sale_dt, "%X-%V")',
                'nm_id' => 'dd.nm_id',
                'vendorCode' => 'cc.vendorCode',
                'title' => 'cc.title',

                'retail_price_full' =>      'SUM(COALESCE(dd.retail_price, 0))'            , 
                'retail_amount_full' =>     'SUM(COALESCE(retail_amount, 0))'           , 
                'ppvz_for_pay_full' =>      'SUM(COALESCE(ppvz_for_pay, 0))'            , 
                'delivery_rub_full' =>      'SUM(COALESCE(delivery_rub, 0))'            , 
                'ppp' => 'pp.p',

                'retail_price' =>           'SUM(COALESCE(dd.retail_price, 0)) * pp.p/100'            , 
                'retail_amount' =>          'SUM(COALESCE(retail_amount, 0)) * pp.p/100'           , 
                'commission_percent' =>     'AVG(COALESCE(commission_percent, 0))'      , 
                'ppvz_spp_prc' =>           'AVG(COALESCE(ppvz_spp_prc, 0))'            , 
                'ppvz_sales_commission' =>  'SUM(COALESCE(ppvz_sales_commission, 0))'   ,  
                'ppvz_reward' =>            'SUM(COALESCE(ppvz_reward, 0)) * pp.p/100'             , 
                'acquiring_fee' =>          'SUM(COALESCE(acquiring_fee, 0)) * pp.p/100'           , 
                'ppvz_vw' =>                'SUM(COALESCE(ppvz_vw, 0)) * pp.p/100'                 , 
                'ppvz_vw_nds' =>            'SUM(COALESCE(ppvz_vw_nds, 0)) * pp.p/100'             , 
                'delivery_rub' =>           'SUM(COALESCE(delivery_rub, 0)) * pp.p/100'            , 
                'rebill_logistic_cost' =>   'SUM(COALESCE(rebill_logistic_cost, 0)) * pp.p/100'    , 
                'ppvz_for_pay' =>           'SUM(COALESCE(ppvz_for_pay, 0)) * pp.p/100'            , 
                'rows_count' =>             'SUM(0)'                                     ,

                'sales_count' => 'SUM(0)',
                'return_count' => 'SUM(0)',
                'retail_sum' => 'SUM(retail_amount)', 
                'for_pay' => 'SUM(ppvz_for_pay)',
                'delivery' => 'SUM(delivery_amount)',
            ])
            ->from(['product_wb_card pp'])
            ->leftJoin('detail_by_period dd', 'pp.wb_nm_id = dd.nm_id')
            ->leftJoin('wbcards cc', 'pp.wb_nm_id = cc.nmID')
            ->where(['pp.product_id' => $productId ]) // $productId
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Логистика'])
            ->groupBy(['week_key', 'nm_id', 'cc.vendorCode', 'cc.title']);


        $dataNmId = (new \yii\db\Query())
            ->select([
                'week_key' => 'week_key',
                'nm_id' => 'nm_id',
                'vendorCode' => 'vendorCode',
                'title' => 'title',

                'retail_price_full' =>      'SUM(retail_price_full)'            , 
                'retail_amount_full' =>     'SUM(retail_amount_full)'           , 
                'ppvz_for_pay_full' =>      'SUM(ppvz_for_pay_full)'            , 
                'delivery_rub_full' =>      'SUM(delivery_rub_full)'            , 
                'ppp' => 'AVG(ppp)',

                'retail_price' =>           'SUM(COALESCE(retail_price, 0))'            , 
                'retail_amount' =>          'SUM(COALESCE(retail_amount, 0))'           , 
                'commission_percent' =>     'SUM(COALESCE(commission_percent, 0))'      , 
                'ppvz_spp_prc' =>           'SUM(COALESCE(ppvz_spp_prc, 0))'            , 
                'ppvz_sales_commission' =>  'SUM(COALESCE(ppvz_sales_commission, 0))'   ,  
                'ppvz_reward' =>            'SUM(COALESCE(ppvz_reward, 0))'             , 
                'acquiring_fee' =>          'SUM(COALESCE(acquiring_fee, 0))'           , 
                'ppvz_vw' =>                'SUM(COALESCE(ppvz_vw, 0))'                 , 
                'ppvz_vw_nds' =>            'SUM(COALESCE(ppvz_vw_nds, 0))'             , 
                'delivery_rub' =>           'SUM(COALESCE(delivery_rub, 0))'            , 
                'rebill_logistic_cost' =>   'SUM(COALESCE(rebill_logistic_cost, 0))'    , 
                'ppvz_for_pay' =>           'SUM(COALESCE(ppvz_for_pay, 0))'            , 
                'rows_count' =>             'SUM(rows_count)'                           ,

                'sales_count' => 'SUM(sales_count)',
                'return_count' => 'SUM(return_count)',
                'retail_sum' => 'SUM(retail_sum)', 
                'for_pay' => 'SUM(for_pay)',
                'delivery' => 'SUM(delivery)',
            ])
            ->from(['u' => $dataNmIdL->union($dataNmIdP, true)])
                ->groupBy(['week_key', 'nm_id', 'vendorCode', 'title'])
                ->orderBy(['week_key' => SORT_ASC])
                ->all();

            $groupedNmId = ArrayHelper::index($dataNmId, null, ['nm_id', 'week_key']);
            $allWeeks = ArrayHelper::getColumn($dataNmId, 'week_key'); 
            $allWeeks = array_unique($allWeeks);
            sort($allWeeks); // Чтобы недели шли по порядку

            $chartTimelineData = $repository->getChartTimelineDataByNmid($nmIdList, $dateFrom, $dateTo);
            $chartCountryData  = $repository->getChartCountryDataByNmid($nmIdList, $dateFrom, $dateTo);
            $chartRegionData   = $repository->getChartRegionDataByNmid($nmIdList, $dateFrom, $dateTo);

    }

    return $this->render('weekly_report', [
        'data' => $data,
        'dataNmId' => $dataNmId,
        'relatedCards' => $relatedCards,
        'groupedNmId' => $groupedNmId,
        'allWeeks' => $allWeeks,
        'productsList' => $productsList,
        'productId' => $productId,
        'chartTimelineData' => $chartTimelineData,
        'chartRegionData' => $chartRegionData,
        'chartCountryData' => $chartCountryData,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
    ]);
}

    public function actionWeeklyReportNmid($nmId = null, $dateFrom = null, $dateTo = null) {

        $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-70 days'));
        $params = \app\components\getDPWidget::getParams(70);
//        echo $params['date_from'];

        $data = [];
        $card = [];
        $dataNmId = [];
        $groupedNmId = [];
        $allWeeks = [];

        $chartTimelineData = [];
        $chartRegionData = [];
        $chartCountryData = [];

        if ($params['nm_id']) {
//            $card = \app\models\WbCard::find()->where(['nmID' => $nmId])->all();
            $card = \app\models\WbCard::findOne(['nmID' => $params['nm_id']]);

            $repository = new \app\repositories\WeeklyReportRepository();
            $nmIdList[0] = $params['nm_id'];
            $dateFrom = $params['date_from'];
            echo $params['date_from'];
            $dateTo   = $params['date_to'];

            $data = $repository->getWeeklyReportByNmid($nmIdList, $dateFrom, $dateTo);
            $chartTimelineData = $repository->getChartTimelineDataByNmid($nmIdList, $dateFrom, $dateTo);
            $chartCountryData  = $repository->getChartCountryDataByNmid($nmIdList, $dateFrom, $dateTo);
            $chartRegionData   = $repository->getChartRegionDataByNmid($nmIdList, $dateFrom, $dateTo);
        }

        return $this->render('weekly_report_nmid', [
            'card' => $card,
            'data' => $data,
            'dateFromWidget' => $dateFrom,
            'chartTimelineData' => $chartTimelineData,
            'chartRegionData' => $chartRegionData,
            'chartCountryData' => $chartCountryData,
        ]);
    } // actionWeeklyReportNmid

}


?>

