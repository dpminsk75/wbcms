<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use app\models\OurWarehouse;

/**
 * Реальные склады компании
 */
class OurWarehouseController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class'=>AccessControl::class,'rules'=>[['allow'=>true,'roles'=>['manageFbsStocks']]]],
            'verbs' => ['class'=>VerbFilter::class,'actions'=>['delete'=>['post'],'toggle-central'=>['post'],'toggle-fbs'=>['post']]],
        ];
    }

    public function actionIndex()
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $query = OurWarehouse::find()->where(['company_id'=>$companyId])->orderBy(['is_central'=>SORT_DESC,'name'=>SORT_ASC]);
        $dataProvider = new ActiveDataProvider(['query'=>$query]);
        return $this->render('index', ['dataProvider'=>$dataProvider]);
    }

    public function actionCreate()
    {
        $model = new OurWarehouse();
        $model->company_id = Yii::$app->companyManager->getCurrentId();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }
        return $this->render('create', ['model'=>$model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }
        return $this->render('update', ['model'=>$model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    public function actionToggleCentral($id)
    {
        $m=$this->findModel($id);
        $m->is_central = $m->is_central ? 0 : 1;
        if ($m->is_central) {
            OurWarehouse::updateAll(['is_central'=>0], ['and',['company_id'=>$m->company_id],['not in','id',[$m->id]]]);
        }
        $m->save(false);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['success'=>true,'is_central'=>(int)$m->is_central];
    }

    public function actionToggleFbs($id)
    {
        $m=$this->findModel($id);
        $m->is_fbs = $m->is_fbs ? 0 : 1;
        $m->save(false);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['success'=>true,'is_fbs'=>(int)$m->is_fbs];
    }

    protected function findModel($id)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $m = OurWarehouse::findOne(['id'=>$id,'company_id'=>$companyId]);
        if (!$m) throw new \yii\web\NotFoundHttpException('Склад не найден');
        return $m;
    }
}
