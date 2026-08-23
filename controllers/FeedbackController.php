<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;

class FeedbackController extends Controller
{
    public function actionGetPopupReport()
    {
        $request = Yii::$app->request;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $nmId = $request->get('nmID'); // Необязательный

        if (!$dateFrom || !$dateTo) {
            return '<div class="alert alert-danger">Не указаны даты для фильтрации</div>';
        }

        // Базовый запрос к таблице wb_feedbacks
        $query = (new Query())
            ->from('wb_feedbacks')
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = wb_feedbacks.nmID')
            ->where(['between', 'createdDate', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        // Если nmID передан, добавляем в условие
        if (!empty($nmId)) {
            $query->andWhere(['wb_feedbacks.nmID' => $nmId]);
        }

        // Агрегируем данные для красивого вывода в попапе
        $stats = $query->select([
            'total_feedbacks' => 'COUNT(*)',
            'avg_valuation' => 'AVG(productValuation)',
            'new_count' => 'SUM(CASE WHEN isNew = 1 THEN 1 ELSE 0 END)'
        ])->one();

        // Также можно вытащить несколько последних отзывов для наглядности
        $feedbacks = $query->select(['createdDate', 'c.nmID', 'card_title' => 'c.title',
                'productValuation', 
                'text', 
                'is_pay', 
                'f_cost', 
                'pros',   
                'cons',
                'userName'])
                ->orderBy([
                    'is_pay' => SORT_DESC, 
                    'createdDate' => SORT_DESC
                ])
            ->all();

        return $this->renderPartial('_popup_report', [
            'stats' => $stats,
            'feedbacks' => $feedbacks,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'nmId' => $nmId,
        ]);
    }
}