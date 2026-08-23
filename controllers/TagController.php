<?php

namespace app\controllers;

use Yii;
use app\models\Tag;
use app\models\WbCard;
use app\models\WbCardSearch;
use app\repositories\WbOrderRepository;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;
use yii\filters\VerbFilter;

class TagController extends Controller
{
    /**
     * @var WbOrderRepository
     */
    public $orderRepository;

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Внедряем репозиторий через конструктор
     */
    public function __construct($id, $module, WbOrderRepository $orderRepository, $config = [])
    {
        $this->orderRepository = $orderRepository;
        parent::__construct($id, $module, $config);
    }

    /**
     * Список тегов
     */
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Tag::find()->orderBy(['priority' => SORT_DESC, 'name' => SORT_ASC]),
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Аналитика (Просмотр тега)
     */
    public function actionView($id = null, $date_range = null)
    {
        $allTags = Tag::find()->orderBy(['priority' => SORT_DESC, 'name' => SORT_ASC])->all();

        // Если ID не задан, берем первый тег
        if ($id === null && !empty($allTags)) {
            $id = $allTags[0]->id;
        }

        $tag = $id ? $this->findModel($id) : null;

        // Обработка дат
        if (!empty($date_range) && strpos($date_range, ' - ') !== false) {
            list($date_from, $date_to) = explode(' - ', $date_range);
        } else {
            $date_from = date('Y-m-d', strtotime('-30 days'));
            $date_to = date('Y-m-d');
            $date_range = $date_from . ' - ' . $date_to;
        }

        $chartData = [];
        $summaryProvider = new ArrayDataProvider(['allModels' => []]);
        $detailProvider = new ArrayDataProvider(['allModels' => []]);
        $detailAgrProvider = new ArrayDataProvider(['allModels' => []]);
        $relatedCards = [];

        if ($tag && !empty($tag->wbCardIds)) {
            $nmIds = $tag->wbCardIds;

            $relatedCards = (new \yii\db\Query())
                ->select(['w.nmId', 'w.title as card_name', 'w.vendorCode as vendorCode'])
                ->from('tag_card_links t')
                ->innerJoin('wbcards w', 't.nmID = w.nmID')
                ->where(['t.tag_id' => $tag->id])
                ->all();

            $summaryData   = $this->orderRepository->getOrdersStats($nmIds, $date_from, $date_to, true, false);
            $detailData    = $this->orderRepository->getOrdersStats($nmIds, $date_from, $date_to, true, true);
            $detailDataAgr = $this->orderRepository->getOrdersStats($nmIds, $date_from, $date_to, false, true);

            $summaryProvider = new ArrayDataProvider([
                'allModels' => $summaryData,
                'pagination' => false,
            ]);

            $detailProvider = new ArrayDataProvider([
                'allModels' => $detailData,
                'pagination' => ['pageSize' => 50],
            ]);

            $detailAgrProvider = new ArrayDataProvider([
                'allModels' => $detailDataAgr,
                'pagination' => ['pageSize' => 50],
            ]);


            $chartData = $this->prepareAmChartsData($detailData);
        }

        return $this->render('view', [
            'tag' => $tag,
            'allTags' => $allTags,
            'relatedCards' => $relatedCards,
            'summaryProvider' => $summaryProvider,
            'detailProvider' => $detailProvider,
            'detailAgrProvider' => $detailAgrProvider,
            'chartData' => $chartData,
            'date_range' => $date_range,
        ]);
    }

    /**
     * Создание тега (Восстановлено)
     */
    public function actionCreate()
    {
        $model = new Tag();
        return $this->saveTag($model);
    }

    /**
     * Редактирование тега (Восстановлено)
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        return $this->saveTag($model);
    }

    /**
     * Общий метод сохранения (с логикой поиска карточек для Drag-and-Drop)
     */
/*
    protected function saveTag($model)
    {
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }

        $wbSearchModel = new WbCardSearch();
        $wbDataProvider = $wbSearchModel->search(Yii::$app->request->queryParams);
        $wbDataProvider->pagination->pageSize = 100;

        return $this->render($model->isNewRecord ? 'create' : 'update', [
            'model' => $model,
            'wbSearchModel' => $wbSearchModel,
            'wbDataProvider' => $wbDataProvider,
        ]);
    }
*/

    protected function saveTag($model)
    {
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }

        $wbSearchModel = new WbCardSearch();
        $wbDataProvider = $wbSearchModel->search(Yii::$app->request->queryParams);
        $wbDataProvider->pagination->pageSize = 100;

        $selectedCards = [];
        if (!empty($model->wbCardIds)) {
            $cardsById = ArrayHelper::index(
                WbCard::find()->where(['nmID' => $model->wbCardIds])->all(),
                'nmID'
            );
            // сохраняем порядок, в котором id лежат в $model->wbCardIds
            foreach ($model->wbCardIds as $nmId) {
                if (isset($cardsById[$nmId])) {
                    $selectedCards[] = $cardsById[$nmId];
                }
            }
        }

        return $this->render($model->isNewRecord ? 'create' : 'update', [
            'model' => $model,
            'wbSearchModel' => $wbSearchModel,
            'wbDataProvider' => $wbDataProvider,
            'selectedCards' => $selectedCards,
        ]);
    }

    /**
     * Удаление
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    /**
     * Подготовка данных для графиков
     */
    protected function prepareAmChartsData($data)
    {
        $result = [];
        // Собираем все уникальные артикулы, которые есть в выборке за период
        $allNmIds = array_unique(array_column($data, 'nm_id'));

        foreach ($data as $row) {
            $ts = strtotime($row['odate']) * 1000;
            
            if (!isset($result[$ts])) {
                    $result[$ts] = [
                        'date' => $ts,
                        'total_cnt' => 0 // Инициализируем сумматор
                    ];
                // Инициализируем каждый артикул нулями для этой даты
                foreach ($allNmIds as $id) {
                    $result[$ts]['value_' . $id] = 0;
                    $result[$ts]['sum_' . $id] = 0;
                }
            }
            
            // Записываем количество (cnt) как основное значение и сумму для подсказки
            $result[$ts]['value_' . $row['nm_id']] = (int)$row['cnt'];
            $result[$ts]['sum_' . $row['nm_id']] = (float)$row['sum_ord'];
            $result[$ts]['total_cnt'] += (int)$row['cnt'];
        }

        // Важно: сортируем по дате, иначе график будет «прыгать» или не отрисуется
        ksort($result);
        return array_values($result);
    }



    /**
     * Поиск модели
     */
    protected function findModel($id)
    {
        if (($model = Tag::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Тег не найден.');
    }


}