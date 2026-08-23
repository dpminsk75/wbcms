<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\ProductionPlanItem;
use app\models\WbCardSearch;
use app\models\Company;

class ProductionPlanController extends Controller
{
    public function actionIndex()
    {
        if (Yii::$app->request->isPost) {
            $this->saveItems(Yii::$app->request->post('ProductionPlanItem', []));
            Yii::$app->session->setFlash('success', 'Список сохранён.');
            return $this->redirect(['index']);
        }

        $itemsQuery = ProductionPlanItem::find()
            ->joinWith('wbCard')
            ->orderBy(['sort_order' => SORT_ASC, '{{%production_plan_item}}.id' => SORT_ASC]);

        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($itemsQuery, 'wbcards');
        }

        $items = $itemsQuery->all();

        $existingNmIds = array_map(fn($i) => $i->nm_id, $items);

        $wbSearchModel = new WbCardSearch();
        $wbSearchModel->pageSize = 30;
        $wbDataProvider = $wbSearchModel->search(Yii::$app->request->queryParams);

        if (Yii::$app->has('companyManager') && isset($wbDataProvider->query)) {
            Yii::$app->companyManager->applyToQuery($wbDataProvider->query);
        }

        // товары, уже добавленные в план, не показываем в "доступных" слева
        if (!empty($existingNmIds) && isset($wbDataProvider->query)) {
            $wbDataProvider->query->andWhere(['not in', 'nmID', $existingNmIds]);
        }

        $showCompanyColumn = Yii::$app->has('companyManager') && Yii::$app->companyManager->isGlobalMode();
        $companyAbbrMap = $showCompanyColumn ? Company::abbreviationMap() : [];

        return $this->render('index', [
            'items' => $items,
            'wbSearchModel' => $wbSearchModel,
            'wbDataProvider' => $wbDataProvider,
            'showCompanyColumn' => $showCompanyColumn,
            'companyAbbrMap' => $companyAbbrMap,
        ]);
    }

    /**
     * Полная замена списка на присланный с формы (как и wbCardIds у тегов):
     * что осталось в правой колонке на момент сабмита - то и сохраняем,
     * остальное удаляется.
     */
    /**
     * Полная замена списка на присланный с формы (как и wbCardIds у тегов):
     * что осталось в правой колонке на момент сабмита - то и сохраняем,
     * остальное удаляется. Строки, не прошедшие валидацию (например, товар
     * другой компании при активном фильтре), молча пропускаются - их нет
     * смысла показывать пользователю в стандартном сценарии, т.к. такое
     * возможно только при подделке запроса, а не через обычный UI.
     */
    private function saveItems(array $rows): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $deleteQuery = ProductionPlanItem::find()
                ->select('{{%production_plan_item}}.id')
                ->joinWith('wbCard');
            if (Yii::$app->has('companyManager')) {
                Yii::$app->companyManager->applyToQuery($deleteQuery, 'wbcards');
            }
            ProductionPlanItem::deleteAll(['id' => $deleteQuery]);

            $order = 0;
            foreach ($rows as $nmId => $fields) {
                $item = new ProductionPlanItem();
                $item->nm_id = (int)$nmId;
                $item->production_days = (int)($fields['production_days'] ?? 20);
                $item->logistics_smolensk_days = (int)($fields['logistics_smolensk_days'] ?? 5);
                $item->logistics_wb_days = (int)($fields['logistics_wb_days'] ?? 5);
                $item->buffer_days = (int)($fields['buffer_days'] ?? 7);
                $item->target_coverage_days = (int)($fields['target_coverage_days'] ?? 90);
                $item->sort_order = $order++;

                if (!$item->validate()) {
                    continue; // например, товар другой компании - пропускаем
                }
                $item->save(false);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
