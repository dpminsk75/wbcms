<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\web\Response;

class WbSalesAnalysisController extends Controller
{
    public function actionIndex()
    {
        $request = Yii::$app->request;

        // 1. Параметры фильтрации
        $dateFrom   = $request->get('date_from', date('Y-m-01'));
//        $dateTo     = $request->get('date_to', date('Y-m-d'));
        $dateTo     = $request->get('date_to', date('Y-m-d', strtotime('-1 day')));
        $reportType = $request->get('report_type', 'revenue'); // revenue или qty
        $topLimit   = (int)$request->get('top_limit', 20);

        $country    = $request->get('country');
        $region     = $request->get('region');
        $oblast     = $request->get('oblast');

        $params = [
/*
            'country'    => $request->get('country'),
            'oblast'     => $request->get('oblast'),
            'region'     => $request->get('region'),
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'reportType' => $reportType,
            'topLimit'   => $topLimit,
            'brand'      => $brand,
            'subject'    => $subject,
            'category'   => $category,
*/
        'dateFrom'   => $request->get('date_from', date('Y-m-01')),
//        'dateTo'     => $request->get('date_to', date('Y-m-d')),
        'dateTo'     => $request->get('date_to', date('Y-m-d', strtotime('-1 day'))),
        'reportType' => $request->get('report_type', 'revenue'),
        'topLimit'   => (int)$request->get('top_limit', 20),
        'brand'      => $request->get('brand'),
        'category'   => $request->get('category'), // Поле category
        'type'       => $request->get('type'),     // Поле subject
        'country'    => $request->get('country'),
        'oblast'     => $request->get('oblast'),
        'region'     => $request->get('region'),
        ];

        // 2. Основной запрос
        $query = (new Query())
            ->select([
                'nm_id' => 's.nmId',
                'card_name' => 'c.title', // По твоим правилам c.title
                'c.vendorCode',
                's.brand', 's.subject', 's.category', 
                'aspp'         => 'AVG(s.spp)',
                'total_sum'    => 'SUM(s.totalPrice)',
                'finished_sum' => 'SUM(s.finishedPrice)',
                'for_pay_sum'  => 'SUM(s.forPay)',
                'disc_sum'     => 'SUM(s.priceWithDisc)',

                'apwd'         => 'AVG(s.priceWithDisc)',
                'afp'          => 'AVG(s.finishedPrice)',
                'aforPay'      => 'AVG(s.forPay)',
                // Логика количества: +1 если продажа (>0), -1 если возврат (<0)
                'sales_qty'    => 'SUM(CASE WHEN s.totalPrice > 0 THEN 1 WHEN s.totalPrice < 0 THEN -1 ELSE 0 END)'
            ])
            ->from(['s' => 'wb_sales'])
            ->innerJoin(['c' => 'wbcards'], 's.nmId = c.nmID') // c.nmID
            ->where(['between', 's.date', $dateFrom, $dateTo])
            ->groupBy(['s.nmId', 'c.title', 'c.vendorCode', 's.brand', 's.subject', 's.category',]);

        // Применяем фильтры
        $query->andFilterWhere(['s.brand' => $params['brand']])
              ->andFilterWhere(['s.category' => $params['category']]) 
              ->andFilterWhere(['s.subject' => $params['type']])
              ->andFilterWhere(['s.countryName' => $country])
              ->andFilterWhere(['s.regionName' => $region])
              ->andFilterWhere(['s.oblastOkrugName' => $oblast]);

        // Сортировка ТОП
        if ($reportType === 'qty') {
            $query->orderBy(['sales_qty' => SORT_DESC]);
        } else {
            $query->orderBy(['finished_sum' => SORT_DESC]);
        }

        $query->limit($topLimit);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false, // Для ТОП отчетов пагинация обычно не нужна
        ]);

        // Данные для выпадающих списков (кэшируем или берем unique)
        $filterData = [
            'brands'     => $this->getUniqueValues('brand'),
            'categories' => $this->getUniqueValues('category'), 
            'types'      => $this->getUniqueValues('subject'),  
            'countries'  => $this->getUniqueValues('countryName'),
        ];


        $selectedOblasts = [];
        if ($params['country'] === 'Россия') {
            $selectedOblasts = \yii\helpers\ArrayHelper::map(
                (new \yii\db\Query())
                    ->select(['oblastOkrugName'])
                    ->from('wb_sales')
                    ->where(['countryName' => 'Россия'])
                    ->distinct()
                    ->orderBy(['oblastOkrugName' => SORT_ASC]) // Сортировка
                    ->all(),
                'oblastOkrugName', 'oblastOkrugName'
            );
        }

        $selectedRegions = [];
        if ($params['country']) {
            $subQuery = (new \yii\db\Query())
                ->select(['regionName'])
                ->from('wb_sales')
                ->where(['countryName' => $params['country']])
                ->distinct()
                ->orderBy(['regionName' => SORT_ASC]); // Сортировка

            if ($params['country'] === 'Россия' && $params['oblast']) {
                $subQuery->andWhere(['oblastOkrugName' => $params['oblast']]);
            }
            $selectedRegions = \yii\helpers\ArrayHelper::map($subQuery->all(), 'regionName', 'regionName');
        }

        $selectedTypes = [];
        if ($params['category']) {
            $selectedTypes = \yii\helpers\ArrayHelper::map(
                (new \yii\db\Query())
                    ->select(['subject'])
                    ->from('wb_sales')
                    ->where(['category' => $params['category']])
                    ->distinct()
                    ->orderBy(['subject' => SORT_ASC])
                    ->all(),
                'subject', 'subject'
            );
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'filterData'   => $filterData,
            'selectedRegions' => $selectedRegions,
            'selectedOblasts' => $selectedOblasts,
            'selectedTypes' => $selectedTypes,
            'params' => $params,
        ]);
    }

    // Вспомогательный метод для получения списков
    protected function getUniqueValues($column)
    {
        return \yii\helpers\ArrayHelper::map(
            (new \yii\db\Query())
                ->select([$column])
                ->from('wb_sales')
                ->distinct()
                ->where(['is not', $column, null])
                ->orderBy([$column => SORT_ASC]) // Добавили сортировку
                ->all(),
            $column, $column
        );
    }

    public function actionGetDistricts()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        
        if (!empty($parents)) {
            $country = $parents[0];
            
            if ($country === 'Россия') {
                $out = (new \yii\db\Query())
                    ->select(['id' => 'oblastOkrugName', 'name' => 'oblastOkrugName'])
                    ->from('wb_sales')
                    ->where(['countryName' => 'Россия'])
                    ->andWhere(['is not', 'oblastOkrugName', null])
                    ->distinct()
                    ->orderBy(['oblastOkrugName' => SORT_ASC]) // Сортировка тут
                    ->all();
                return ['output' => $out, 'selected' => ''];
            }
        }
        return ['output' => [], 'selected' => ''];
    }

    public function actionGetRegions()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        
        if (!empty($parents)) {
            $country = $parents[0];
            $district = $parents[1] ?? null;

            $query = (new \yii\db\Query())
                ->select(['id' => 'regionName', 'name' => 'regionName'])
                ->from('wb_sales')
                ->where(['countryName' => $country])
                ->distinct()
                ->orderBy(['regionName' => SORT_ASC]); // Сортировка тут

            if ($country === 'Россия' && !empty($district)) {
                $query->andWhere(['oblastOkrugName' => $district]);
            }

            return ['output' => $query->all(), 'selected' => ''];
        }
        return ['output' => [], 'selected' => ''];
    }

    public function actionGetTypes()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        
        if (!empty($parents)) {
            $category = $parents[0];
            
            $out = (new \yii\db\Query())
                ->select(['id' => 'subject', 'name' => 'subject'])
                ->from('wb_sales')
                ->where(['category' => $category])
                ->andWhere(['is not', 'subject', null])
                ->distinct()
                ->orderBy(['subject' => SORT_ASC])
                ->all();
                
            return ['output' => $out, 'selected' => ''];
        }
        
        return ['output' => [], 'selected' => ''];
    }


    public function actionGeoReport()
    {
        $request = Yii::$app->request;

        $params = [
            'dateFrom'   => $request->get('date_from', date('Y-m-01')),
            'dateTo'     => $request->get('date_to', date('Y-m-d', strtotime('-1 day'))),
            'brand'      => $request->get('brand'),
            'category'   => $request->get('category'),
            'type'       => $request->get('type'),
            'country'    => $request->get('country'),
            'oblast'     => $request->get('oblast'),
            'region'     => $request->get('region'),
            'city'       => $request->get('city'),
        ];

        // Основной запрос к финансовым отчетам
        $query = (new Query())
            ->select([
                'nm_id'        => 'dbp.nm_id',
                'card_name'    => 'c.title', // Правило: c.title
                'brand'        => 'c.brand',
                'vendorCode'   => 'c.vendorCode',
                'cat_name'     => 'cat.parent_name',
                'sub_name'     => 'cat.subject_name',
                'sales_qty'    => "SUM(CASE WHEN dbp.doc_type_name = 'Продажа' THEN dbp.quantity ELSE -dbp.quantity END)",
                'finished_sum' => 'SUM(dbp.retail_price_withdisc_rub)',
                'aspp'         => 'AVG(dbp.ppvz_spp_prc)',
            ])
            ->from(['dbp' => 'detail_by_period'])
            ->innerJoin(['c' => 'wbcards'], 'dbp.nm_id = c.nmID') // Правило: c.nmID
            ->leftJoin(['cat' => 'wb_subject_catalog'], 'c.subjectID = cat.subject_id')
            ->innerJoin(['dac' => 'dadata_address_cache'], 'dbp.address_id = dac.id')
            ->where(['between', 'dbp.sale_dt', $params['dateFrom'], $params['dateTo']]);

        // Фильтрация товаров
        if ($params['brand'])    $query->andWhere(['c.brand' => $params['brand']]);
        if ($params['category']) $query->andWhere(['cat.parent_name' => $params['category']]);
        if ($params['type'])     $query->andWhere(['cat.subject_name' => $params['type']]);

        // Фильтрация ГЕО
        if ($params['country']) $query->andWhere(['dac.country' => $params['country']]);
        if ($params['oblast'])  $query->andWhere(['dac.federal_district' => $params['oblast']]);
        if ($params['region'])  $query->andWhere(['dac.region' => $params['region']]);
        if ($params['city'])    $query->andWhere(['COALESCE(dac.city, dac.settlement)' => $params['city']]);

        $query->groupBy(['dbp.nm_id', 'c.title', 'c.brand', 'c.vendorCode', 'cat.parent_name', 'cat.subject_name']);
        $query->orderBy(['finished_sum' => SORT_DESC]);

        // Данные для выпадающих списков
        $brands = ArrayHelper::map(
            (new Query())->select(['brand'])->from('wbcards')->distinct()
                ->where(['is not', 'brand', null])->orderBy(['brand' => SORT_ASC])->all(),
            'brand', 'brand'
        );

        $categories = ArrayHelper::map(
            (new Query())->select(['parent_name'])->from('wb_subject_catalog')
                ->innerJoin('wbcards', 'wbcards.subjectID = wb_subject_catalog.subject_id')
                ->distinct()->orderBy(['parent_name' => SORT_ASC])->all(),
            'parent_name', 'parent_name'
        );

        // Восстановление списков DepDrop после POST/GET
        $selectedTypes = [];
        if ($params['category']) {
            $selectedTypes = ArrayHelper::map(
                (new Query())
                    ->select(['cat.subject_name'])
                    ->from(['cat' => 'wb_subject_catalog'])
                    // Связываем с карточками, чтобы оставить только те типы, которые у нас есть
                    ->innerJoin(['c' => 'wbcards'], 'c.subjectID = cat.subject_id')
                    ->where(['cat.parent_name' => $params['category']])
                    ->distinct()
                    ->orderBy(['cat.subject_name' => SORT_ASC])
                    ->all(),
                'subject_name', 'subject_name'
            );
        }
        // ГЕО списки (для сохранения состояния)
        $selectedOblasts = $params['country'] === 'Россия' ? $this->getDadataMap('federal_district', ['country' => 'Россия']) : [];
        $selectedRegions = $params['country'] ? $this->getDadataMap('region', ['country' => $params['country']]) : [];
        $selectedCities  = $params['region'] ? $this->getDadataMap('COALESCE(city, settlement)', ['region' => $params['region']]) : [];

        return $this->render('geo-report', [
            'dataProvider' => new ArrayDataProvider(['allModels' => $query->all(), 'pagination' => false]),
            'params'       => $params,
            'brands'       => $brands,
            'categories'   => $categories,
            'selectedTypes' => $selectedTypes,
            'countries'    => ArrayHelper::map((new Query())->select(['country'])->from('dadata_address_cache')->distinct()->all(), 'country', 'country'),
            'selectedOblasts' => $selectedOblasts,
            'selectedRegions' => $selectedRegions,
            'selectedCities'  => $selectedCities,
        ]);
    }

    public function actionGetTypesByCategory()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        if (!empty($parents)) {
            $catName = $parents[0];
            $out = (new Query())
                ->select(['id' => 'subject_name', 'name' => 'subject_name'])
                ->from('wb_subject_catalog')
                ->innerJoin('wbcards', 'wbcards.subjectID = wb_subject_catalog.subject_id')
                ->where(['parent_name' => $catName])
                ->distinct()->orderBy(['subject_name' => SORT_ASC])->all();
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => [], 'selected' => ''];
    }

    // AJAX для городов
    public function actionGetCitiesDadata()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        if (!empty($parents)) {
            $region = $parents[0];
            $out = (new Query())
                ->select(['id' => 'COALESCE(city, settlement)', 'name' => 'COALESCE(city, settlement)'])
                ->from('dadata_address_cache')
                ->where(['region' => $region])
                ->andWhere(['or', ['is not', 'city', null], ['is not', 'settlement', null]])
                ->distinct()->orderBy(['name' => SORT_ASC])->all();
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => [], 'selected' => ''];
    }

    protected function getDadataMap($col, $where, $alias = null) {
        $nameCol = $alias ?: $col;
        return ArrayHelper::map(
            (new Query())->select(['id' => $col, 'name' => $col])->from('dadata_address_cache')
                ->where($where)->distinct()->orderBy(['name' => SORT_ASC])->all(),
            'id', 'name'
        );
    }

    // Стандартные методы для DepDrop Dadata (Округа и Регионы)
    public function actionGetDistrictsDadata()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        if (!empty($parents) && $parents[0] === 'Россия') {
            $out = (new Query())->select(['id' => 'federal_district', 'name' => 'federal_district'])
                ->from('dadata_address_cache')->where(['country' => 'Россия'])
                ->andWhere(['is not', 'federal_district', null])->distinct()->all();
            return ['output' => $out, 'selected' => ''];
        }
        return ['output' => [], 'selected' => ''];
    }

    public function actionGetRegionsDadata()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $parents = Yii::$app->request->post('depdrop_parents');
        if (!empty($parents)) {
            $country = $parents[0];
            $district = $parents[1] ?? null;
            $query = (new Query())->select(['id' => 'region', 'name' => 'region'])->from('dadata_address_cache')->where(['country' => $country])->distinct();
            if ($country === 'Россия' && $district) $query->andWhere(['federal_district' => $district]);
            return ['output' => $query->all(), 'selected' => ''];
        }
        return ['output' => [], 'selected' => ''];
    }


}