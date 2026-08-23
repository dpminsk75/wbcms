<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class ProductWbCardSearch extends ProductWbCard
{
    public $productName;
    public $vendorCode;

    public function rules()
    {
        return [
            [['product_id', 'wb_nm_id', 'q'], 'integer'],
            [['p'], 'number'],
            [['productName', 'vendorCode'], 'safe'],
        ];
    }


    public function search($params)
    {
        // 1. Отбираем только записи с type = 1
        $query = ProductWbCard::find()
            ->joinWith(['product', 'wbCard']) // Важно: подгружаем связи
            ->where(['{{%product_wb_card}}.type' => 1]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                // ИЗМЕНЕНО: сортируем по карточке, чтобы работала группировка в GridView
                'defaultOrder' => ['wb_nm_id' => SORT_DESC],
                'attributes' => [
                    'wb_nm_id',
                    'q',
                    'p',
                    // Настройка сортировки по имени товара
                    'productName' => [
                        'asc' => ['product.name' => SORT_ASC],
                        'desc' => ['product.name' => SORT_DESC],
                    ],
                    // Добавим сортировку по артикулу, если захочешь кликнуть по шапке
                    'vendorCode' => [
                        'asc' => ['wbcards.vendorCode' => SORT_ASC],
                        'desc' => ['wbcards.vendorCode' => SORT_DESC],
                    ],
                ]
            ]
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Фильтрация
        $query->andFilterWhere(['{{%product_wb_card}}.wb_nm_id' => $this->wb_nm_id])
              ->andFilterWhere(['q' => $this->q]);

        $query->andFilterWhere(['like', 'product.name', $this->productName])
              ->andFilterWhere(['like', 'wbcards.vendorCode', $this->vendorCode]);

        return $dataProvider;
    }

}