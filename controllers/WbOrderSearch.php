<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\WbOrder;
// Можно импортировать Yii здесь:
use Yii; 

class WbOrderSearch extends WbOrder
{
    public $cardTitle;

    public function rules()
    {
        return [
            [['cardTitle'], 'safe'],
            [['id', 'nm_id', 'discount_percent', 'is_cancel'], 'integer'],
            [['g_number', 'date', 'supplier_article', 'brand', 'category', 'subject'], 'safe'],
            [['total_price', 'finished_price'], 'number'],
        ];
    }

    public function search($params)
    {
        $query = WbOrder::find()->joinWith(['card']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['date' => SORT_DESC]],
            'pagination' => [
                'pageSize' => 100, // Лимит 100 строк
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['wb_order.nm_id' => $this->nm_id]);

        // Чтобы избежать ошибки "Ambiguous column", везде добавляем префикс таблицы
        $query->andFilterWhere([
            'wb_order.id' => $this->id,
            'wb_order.nm_id' => $this->nm_id,
            'wb_order.is_cancel' => $this->is_cancel,
        ]);

        $query->andFilterWhere(['like', 'wb_order.date', $this->date])
            ->andFilterWhere(['like', 'wb_order.g_number', $this->g_number])
            ->andFilterWhere(['like', 'wb_order.brand', $this->brand])
            ->andFilterWhere(['like', 'wb_order.category', $this->category])
            ->andFilterWhere(['like', 'wb_cards.title', $this->cardTitle]) // Поиск по связанной таблице
            ->andFilterWhere(['like', 'wb_order.supplier_article', $this->supplier_article]);

        return $dataProvider;
    }
}