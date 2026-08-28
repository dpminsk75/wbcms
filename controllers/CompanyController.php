<?php
namespace app\controllers;

use Yii;
use app\models\Company;
use app\models\CompanySearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

class CompanyController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin', 'manageCompanies'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new CompanySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    public function actionView($id)
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate()
    {
        $model = new Company();
        $model->is_active = 1;
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Компания создана');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('create', compact('model'));
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Сохранено');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('update', compact('model'));
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        // мягкое удаление — деактивация, чтобы не ломать FK detail_by_period.company_id
        $model->is_active = 0;
        $model->save(false);
        Yii::$app->session->setFlash('warning', 'Компания деактивирована');
        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = Company::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Компания не найдена');
    }
}
