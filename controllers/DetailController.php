<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;

class DetailController extends Controller
{
    public function actionGetPopupReport()
    {
        $request = Yii::$app->request;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $nmId = $request->get('nmID');
        $type = $request->get('type'); // 'shf' или 'udr'

        if (empty($dateFrom) || empty($dateTo) || empty($type)) {
            return '<div class="alert alert-danger">Не переданы обязательные параметры.</div>';
        }

        // Базовый запрос с правильным полем sale_dt
        $query = (new Query())
            ->from('detail_by_period')
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = detail_by_period.nm_id')
            ->where(['between', 'detail_by_period.sale_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        // Настраиваем фильтр в зависимости от типа
        if ($type === 'shf') {
            $query->andWhere(['detail_by_period.supplier_oper_name' => 'Штраф']);
            $sumField = 'detail_by_period.penalty';
        } else {
            $query->andWhere(['detail_by_period.supplier_oper_name' => 'Удержание']);
            $sumField = 'detail_by_period.deduction';
        }

        if (!empty($nmId)) {
            $query->andWhere(['detail_by_period.nm_id' => $nmId]);
        }

        // Считаем общую сумму и количество
        $sumQuery = clone $query;
        $totalSum = $sumQuery->sum($sumField) ?? 0;
        $totalCount = $sumQuery->count() ?? 0;

        // Выборка полей для таблицы с универсальным алиасом row_date
        $items = $query->select([
                'row_date' => 'detail_by_period.sale_dt', // Используем sale_dt
                'detail_by_period.nm_id as nmID',
                'amount' => $sumField,
                'reason' => 'detail_by_period.bonus_type_name',
                'card_title' => 'c.title'
            ])
            ->orderBy(['detail_by_period.sale_dt' => SORT_DESC])
            ->limit(150)
            ->all();

        return $this->renderPartial('_popup_detail', [
            'items' => $items,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'nmId' => $nmId,
            'type' => $type,
            'totalSum' => $totalSum,
            'totalCount' => $totalCount,
        ]);
    }
}