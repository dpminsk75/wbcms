<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\web\Response;
use app\models\ProductType;
use app\models\Brand;
use app\models\WbCardSearch;
use app\models\ProductWbCard;
use app\models\Product;

class ProductController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Список товаров.
     *
     * @return string
     */
/*
    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Product::find()->with(['productType', 'brand', 'wbCards']),
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
*/
/*
    public function actionIndex(): string
    {
        // Используем специально созданную модель поиска вместо прямого запроса
        $searchModel = new \app\models\ProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
*/
    public function actionIndex(): string
    {
        $searchModel = new \app\models\ProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // Подготавливаем списки для фильтров
        $productTypesList = \app\models\ProductType::find()
            ->select(['name', 'id'])
            ->indexBy('id')
            ->column();

        $brandsList = \app\models\Brand::find()
            ->select(['name', 'id'])
            ->indexBy('id')
            ->column();

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'productTypesList' => $productTypesList, // Передаем во View
            'brandsList' => $brandsList,             // Передаем во View
        ]);
    }

    /**
     * Создание нового товара.
     *
     * @return string|Response
     */
    public function actionCreate()
    {
        $model = new Product();

        // Поиск карточек WB для блока привязки
        $wbSearchModel = new WbCardSearch();
        $wbDataProvider = $wbSearchModel->search(Yii::$app->request->queryParams);

            if ($model->load(Yii::$app->request->post())) {
                // Явно загружаем массив ID карточек из POST, если load() его проигнорировал
                $model->wbCardIds = Yii::$app->request->post('Product')['wbCardIds'] ?? [];

                if ($model->validate()) {
                    if ($model->save(false)) {
                        // Очищаем старые связи
                        ProductWbCard::deleteAll(['product_id' => $model->id]);
                        
                        if (is_array($model->wbCardIds)) {
                            // Убираем дубликаты и пустые значения
                            $model->wbCardIds = array_unique(array_filter($model->wbCardIds));
                            foreach ($model->wbCardIds as $nmId) {
                                $link = new ProductWbCard();
                                $link->product_id = $model->id;
                                $link->wb_nm_id = (int)$nmId;

                                $link->type = 0;
                                $link->q = 1;
                                $link->p = 100;

                                $link->save(false);
                            }
                        }
                        return $this->redirect(['index']);
                    }
                }
            }

        // Справочники для выпадающих списков
        $productTypes = ProductType::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();

        $brands = Brand::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();

        return $this->render('create', [
            'model' => $model,
            'selectedCards' => [], // Обязательно передаем пустой массив для нового товара
            'productTypes' => $productTypes,
            'brands' => $brands,
            'wbSearchModel' => $wbSearchModel,
            'wbDataProvider' => $wbDataProvider,
        ]);
    }

    /**
     * Редактирование товара.
     *
     * @param int $id
     * @return string|Response
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionUpdate(int $id)
    {

        $model = Product::findOne($id);
        if ($model === null) {
            throw new \yii\web\NotFoundHttpException('Товар не найден.');
        }

//        $selectedCards = $model->wbCards;
        $selectedCards = $model->getWbCards()
            ->innerJoin('product_wb_card', 'product_wb_card.wb_nm_id = wbcards.nmID')
            ->where(['product_wb_card.product_id' => $id, 'product_wb_card.type' => 0])
            ->all();

        $model->wbCardIds = array_map(function($card) { return $card->nmID; }, $selectedCards);

//        echo '<pre> 1111 <br/ >'; var_dump($model->wbCardIds); 

        $wbSearchModel = new WbCardSearch();
        $wbDataProvider = $wbSearchModel->search(Yii::$app->request->queryParams);

            if ($model->load(Yii::$app->request->post())) {
                // Явно загружаем массив ID карточек из POST, если load() его проигнорировал
                $model->wbCardIds = Yii::$app->request->post('Product')['wbCardIds'] ?? [];
                
                if ($model->validate()) {
                    if ($model->save(false)) {
                        // Очищаем старые связи
                        ProductWbCard::deleteAll(['product_id' => $model->id]);
                        
                        if (is_array($model->wbCardIds)) {
                            // Убираем дубликаты и пустые значения
                            $model->wbCardIds = array_unique(array_filter($model->wbCardIds));
                            foreach ($model->wbCardIds as $nmId) {
                                $link = new ProductWbCard();
                                $link->product_id = $model->id;
                                $link->wb_nm_id = (int)$nmId;
                                $link->type = 0;
                                $link->q = 1;
                                $link->p = 100;
                                $link->save(false);
                            }
                        }
                        return $this->redirect(['index']);
                    }
                }
            }

        $productTypes = ProductType::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();

        $brands = Brand::find()
            ->select(['name', 'id'])
            ->orderBy(['name' => SORT_ASC])
            ->indexBy('id')
            ->column();

        return $this->render('create', [
            'model' => $model,
            'selectedCards' => $selectedCards, // Передаем данные о карточках во вьюху
            'productTypes' => $productTypes,
            'brands' => $brands,
            'wbSearchModel' => $wbSearchModel,
            'wbDataProvider' => $wbDataProvider,

        ]);
    }
}

