<?php

namespace app\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\StockSnapshot;

class StockSnapshotSearch extends StockSnapshot
{
    public $vendorCodeOrTitle;

    public function rules()
    {
        return [
            [['nm_id'], 'safe'],
            [['period_date', 'vendorCodeOrTitle'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = StockSnapshot::find()->joinWith('wbCard');

        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($query, 'wbcards');
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
            'sort' => [
                'defaultOrder' => ['period_date' => SORT_DESC],
                'attributes' => [
                    'period_date',
                    'qty_start',
                    'wbCard.vendorCode' => [
                        'asc' => ['wbcards.vendorCode' => SORT_ASC],
                        'desc' => ['wbcards.vendorCode' => SORT_DESC],
                        'label' => 'Артикул',
                    ],
                    'wbCard.nmID' => [
                        'asc' => ['wbcards.nmID' => SORT_ASC],
                        'desc' => ['wbcards.nmID' => SORT_DESC],
                        'label' => 'nmID',
                    ],
                    'wbCard.title' => [
                        'asc' => ['wbcards.title' => SORT_ASC],
                        'desc' => ['wbcards.title' => SORT_DESC],
                        'label' => 'Название',
                    ],
                ],
            ],
        ]);

        $this->load($params, ''); // Загружает параметры в свойства модели

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Используем period_date из queryParams, который уже содержит YYYY-MM-01
        $filterPeriodDate = $params['StockSnapshotSearch']['period_date'] ?? $this->period_date;

        if (!empty($filterPeriodDate)) {
            $startDate = date('Y-m-01', strtotime($filterPeriodDate));
            $endDate = date('Y-m-t', strtotime($filterPeriodDate));
            $query->andFilterWhere(['between', 'stock_snapshot.period_date', $startDate, $endDate]);
        }

        if (!empty($this->vendorCodeOrTitle)) {
            $query->andFilterWhere(['or',
                ['like', 'wbcards.vendorCode', $this->vendorCodeOrTitle],
                ['like', 'wbcards.title', $this->vendorCodeOrTitle],
                ['like', 'wbcards.nmID', $this->vendorCodeOrTitle],
            ]);
        }

        return $dataProvider;
    }
}