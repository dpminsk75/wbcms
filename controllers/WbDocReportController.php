<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\data\ArrayDataProvider;
use app\models\OurWarehouse;
use app\services\StockReportService;

class WbDocReportController extends Controller
{
    public function behaviors()
    {
        return [
            'access'=>['class'=>AccessControl::class,'rules'=>[['allow'=>true,'roles'=>['manageFbsStocks']]]],
        ];
    }

    public function actionBalance($warehouseId='all', $date=null, $q=null)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->orderBy(['name'=>SORT_ASC])->all();
        $warehouseId = Yii::$app->request->get('warehouseId', $warehouseId);
        $date = Yii::$app->request->get('date', $date);
        $q = trim((string)Yii::$app->request->get('q', $q));
        $onlyAvailable = (bool)Yii::$app->request->get('onlyAvailable', 0);
        $rows = StockReportService::balance($companyId, $warehouseId, $date, $q ?: null, $onlyAvailable);
        $dataProvider = new ArrayDataProvider(['allModels'=>$rows,'pagination'=>['pageSize'=>100],'sort'=>false]);
        return $this->render('balance', ['dataProvider'=>$dataProvider,'warehouses'=>$warehouses,'warehouseId'=>$warehouseId,'date'=>$date,'q'=>$q,'onlyAvailable'=>$onlyAvailable,'rows'=>$rows]);
    }

    public function actionTurnover($warehouseId='all', $dateFrom=null, $dateTo=null, $q=null)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->orderBy(['name'=>SORT_ASC])->all();
        $warehouseId = Yii::$app->request->get('warehouseId', $warehouseId);
        $dateFrom = Yii::$app->request->get('dateFrom', $dateFrom) ?: date('Y-m-01');
        $dateTo = Yii::$app->request->get('dateTo', $dateTo) ?: date('Y-m-d');
        $q = trim((string)Yii::$app->request->get('q', $q));
        $rows = StockReportService::turnover($companyId, $warehouseId, $dateFrom, $dateTo, $q ?: null);
        $dataProvider = new ArrayDataProvider(['allModels'=>$rows,'pagination'=>['pageSize'=>100],'sort'=>false]);
        return $this->render('turnover', ['dataProvider'=>$dataProvider,'warehouses'=>$warehouses,'warehouseId'=>$warehouseId,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,'q'=>$q]);
    }

    public function actionCard($sku=null, $warehouseId='all', $dateFrom=null, $dateTo=null)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->orderBy(['name'=>SORT_ASC])->all();
        $sku = trim((string)Yii::$app->request->get('sku', $sku));
        $warehouseId = Yii::$app->request->get('warehouseId', $warehouseId);
        $dateFrom = Yii::$app->request->get('dateFrom', $dateFrom);
        $dateTo = Yii::$app->request->get('dateTo', $dateTo);
        $rows = [];
        if ($sku) $rows = StockReportService::card($companyId, $sku, $warehouseId, $dateFrom, $dateTo);
        $dataProvider = new ArrayDataProvider(['allModels'=>$rows,'pagination'=>['pageSize'=>100],'sort'=>false]);
        return $this->render('card', ['dataProvider'=>$dataProvider,'warehouses'=>$warehouses,'warehouseId'=>$warehouseId,'sku'=>$sku,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo]);
    }

    public function actionNonliquid($warehouseId='all', $days=30, $q=null)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->orderBy(['name'=>SORT_ASC])->all();
        $warehouseId = Yii::$app->request->get('warehouseId', $warehouseId);
        $days = (int)Yii::$app->request->get('days', $days);
        if ($days<1) $days=30;
        if ($days>365) $days=365;
        $q = trim((string)Yii::$app->request->get('q', $q));
        $rows = StockReportService::nonLiquid($companyId, $warehouseId, $days, $q ?: null);
        $dataProvider = new ArrayDataProvider(['allModels'=>$rows,'pagination'=>['pageSize'=>100],'sort'=>false]);
        return $this->render('nonliquid', ['dataProvider'=>$dataProvider,'warehouses'=>$warehouses,'warehouseId'=>$warehouseId,'days'=>$days,'q'=>$q]);
    }
}
