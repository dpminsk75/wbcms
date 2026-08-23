<?php

namespace app\models\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\StockMovement;

class StockMovementSearch extends StockMovement
{
    public $vendorCodeOrTitle;

    public function rules()
    {
        return [
            [['nm_id', 'type'], 'safe'],
            [['movement_date', 'vendorCodeOrTitle'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = StockMovement::find()->joinWith('wbCard')->orderBy(['movement_date' => SORT_DESC, 'id' => SORT_DESC]);

        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($query, 'wbcards');
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params, '');

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['stock_movement.type' => $this->type]);
        $query->andFilterWhere(['stock_movement.movement_date' => $this->movement_date]);

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
