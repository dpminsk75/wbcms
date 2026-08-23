<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\WbOrder;
use Yii;

class WbOrderSearch extends WbOrder
{
    public $cardTitle;

    public function rules()
    {
        return [
            [['cardTitle'], 'safe'],
            [['id', 'nm_id', 'is_cancel'], 'integer'],
            [['g_number', 'date', 'supplier_article', 'brand', 'category', 'subject'], 'safe'],
            [['total_price', 'finished_price'], 'number'],
        ];
    }

    public function search($params)
    {
        // Используем asArray() для экономии памяти и joinWith для связи
        $query = WbOrder::find()->joinWith(['card']); 

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['date' => SORT_DESC],
                'attributes' => [
                    'date',
                    'nm_id',
                    'finished_price',
                    'cardTitle' => [
                        'asc' => ['wbcards.title' => SORT_ASC],
                        'desc' => ['wbcards.title' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 100, // Устанавливаем 100 строк на страницу
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Фильтрация с использованием префиксов таблиц
        $query->andFilterWhere([
            'wb_order.nm_id' => $this->nm_id,
            'wb_order.is_cancel' => $this->is_cancel,
        ]);

        $query->andFilterWhere(['like', 'wb_order.g_number', $this->g_number])
              ->andFilterWhere(['like', 'wb_order.supplier_article', $this->supplier_article])
              ->andFilterWhere(['like', 'wb_order.brand', $this->brand])
              ->andFilterWhere(['like', 'wb_order.category', $this->category])
              ->andFilterWhere(['like', 'wbcards.title', $this->cardTitle])
              ->andFilterWhere(['like', 'wb_order.date', $this->date]);

       $query->andFilterWhere(['like', 'wbcards.title', $this->cardTitle]);

        return $dataProvider;
    }

    
}