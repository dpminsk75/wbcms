<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;
use yii\db\Expression;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;


class GeoMapReportController extends Controller
{
    public function actionIndex($dateFrom = null, $dateTo = null, $nmid = null)
    {
        $request = Yii::$app->request->get();
        
        // Устанавливаем даты по умолчанию (например, текущий месяц), если они не переданы
/*
        $dateFrom = $dateFrom ?? $request['dateFrom'] ?? date('Y-m-01'); // С 1-го числа текущего месяца
        $dateTo = $dateTo ?? $request['dateTo'] ?? date('Y-m-d');
        $nmid = $nmid ?? $request['nmid'] ?? null;
*/
        $params = \app\components\getDPWidget::getParams(30);
        $nmid = $params['nm_id'];
        $dateFrom = $params['date_from'];
        $dateTo = $params['date_to'];

        $mapData = [];
        $GridProvider = [];
        $CountryProvider = [];

        if ($nmid) {
        // Определяем финансовые показатели для карты
            $commonSelect = [
                'retail_amount'      => 'SUM(COALESCE(dd.retail_amount, 0))',
                'for_pay'            => 'SUM(COALESCE(dd.ppvz_for_pay, 0))',

                'retail_price'       => 'SUM(COALESCE(dd.retail_price, 0))'            , 
                'commission_percent' => 'AVG(COALESCE(dd.commission_percent, 0))'      , 
                'ppvz_spp_prc'       => 'AVG(COALESCE(dd.ppvz_spp_prc, 0))'            , 
    //            'delivery'      => 'SUM(COALESCE(dd.delivery_amount, 0))',

                // Гео-данные
                'name'          => 'd.region',
                'hc_key'        => 'LOWER(d.region_iso_code)',
                // Количество именно продаж
                'sales_count'   => 'SUM(CASE WHEN dd.supplier_oper_name = "Продажа" THEN 1 ELSE 0 END)',
            ];

            $query = (new Query())
                ->select($commonSelect)
                ->from(['dd' => 'detail_by_period'])
                // Связываем по адресу. Если в detail_by_period адрес в другом поле — замените 'address'
                ->leftJoin(['d' => 'dadata_address_cache'], 'dd.address_id = d.id')
                ->where(['dd.supplier_oper_name' => 'Продажа'])
                ->andWhere(['is not', 'd.region_iso_code', null]);

            // Фильтр по дате (обязательный)
            $query->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo]);

            // Опциональный фильтр по Артикулу (nmID)
            if ($nmid) {
                $query->andWhere(['dd.nm_id' => $nmid]);
            }

            $mapData = $query->groupBy(['d.region', 'd.region_iso_code'])->all();

            $CountryQuery = (new Query())
                ->select([
                    'country'            => 'd.country',
                    'region'             => 'd.region',
                    'retail_amount'      => new Expression('SUM(COALESCE(dd.retail_amount, 0))'),
                    'for_pay'            => new Expression('SUM(COALESCE(dd.ppvz_for_pay, 0))'),
                    'retail_price'       => new Expression('SUM(COALESCE(dd.retail_price, 0))'),
                    'commission_percent' => new Expression('AVG(COALESCE(dd.commission_percent, 0))'),
                    'ppvz_spp_prc'       => new Expression('AVG(COALESCE(dd.ppvz_spp_prc, 0))'),
                    'sales_count'        => new Expression('COUNT(*)'),
                ])
                ->from(['dd' => 'detail_by_period'])
                ->leftJoin(['d' => 'dadata_address_cache'], 'dd.address_id = d.id')
                ->where(['dd.supplier_oper_name' => 'Продажа'])
                ->andWhere(['!=', 'd.country', 'Россия'])
                ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo]);

            // Добавляем фильтр по артикулу, если он передан
            if ($nmid) {
                $CountryQuery->andWhere(['dd.nm_id' => $nmid]);
            }

            $CountryResults = $CountryQuery
                ->groupBy(['d.country', 'd.region'])
                ->orderBy([
                    'd.country' => SORT_ASC, 
                    'sales_count' => SORT_DESC
                ])
                ->all();

            $GridProvider = new ArrayDataProvider([
                'allModels' => $mapData,
                'sort' => [
                    'attributes' => ['name', 'sales_count'],
                    'defaultOrder' => ['sales_count' => SORT_DESC],
                ],
                'pagination' => ['pageSize' => 100], // Обычно для статистики за период пагинация не нужна
            ]);

            $CountryProvider = new ArrayDataProvider([
                'allModels' => $CountryResults,
                'sort' => [
                    'attributes' => ['country', 'region', 'sales_count'],
                    'defaultOrder' => ['sales_count' => SORT_DESC],
                ],
                'pagination' => ['pageSize' => 100], // Обычно для статистики за период пагинация не нужна
            ]);
        }

        return $this->render('index', [
            'mapData' => $mapData,
            'GridProvider' => $GridProvider,
            'CountryProvider' => $CountryProvider,
            'params' => [
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'nmid'     => $nmid,
            ]
        ]);
    }
}

