<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\WbSales;

class WbSalesSearch extends WbSales
{
    /**
     * Правила валидации для фильтров
     */
    public function rules()
    {
        return [
            [['nmId', 'incomeID', 'discountPercent', 'isSupply', 'isRealization'], 'integer'],
            [
                [
                    'saleID', 'srid', 'number', 'date', 'lastChangeDate', 
                    'supplierArticle', 'techSize', 'barcode', 'warehouseName', 
                    'warehouseType', 'countryName', 'regionName', 'oblastOkrugName', 
                    'subject', 'category', 'brand', 'orderType', 'gNumber'
                ], 
                'safe'
            ],
            [['totalPrice','priceWithDisc', 'finishedPrice', 'forPay', 'paymentSaleAmount'], 'number'],
        ];
    }

    /**
     * Метод поиска с применением фильтров
     */
    public function search($params)
    {
        $query = WbSales::find()->alias('s')->joinWith(['card c']);

        $query->select([
            's.*',           // Все поля из wb_sales
            'card_nmid' => 'c.nmid', // Поле nmid из wb_cards с новым именем (алиасом)
            'cardTitle' => 'c.title',
        ]);

        $query->andFilterWhere(['s.nmId' => $this->nmId]); 

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['date' => SORT_DESC]],
            'pagination' => ['pageSize' => 20],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Фильтрация по точным значениям
        $query->andFilterWhere([
            's.nmId' => $this->nmId,
            'incomeID' => $this->incomeID,
            'isSupply' => $this->isSupply,
            'isRealization' => $this->isRealization,
            'totalPrice' => $this->totalPrice,
            'finishedPrice' => $this->finishedPrice,
        ]);

        // Фильтрация по LIKE (частичное совпадение)
        $query->andFilterWhere(['like', 'saleID', $this->saleID])
              ->andFilterWhere(['like', 'srid', $this->srid])
              ->andFilterWhere(['like', 'number', $this->number])
              ->andFilterWhere(['like', 'supplierArticle', $this->supplierArticle])
              ->andFilterWhere(['like', 'barcode', $this->barcode])
              ->andFilterWhere(['like', 'warehouseName', $this->warehouseName])
              ->andFilterWhere(['like', 'warehouseType', $this->warehouseType])
              ->andFilterWhere(['like', 'countryName', $this->countryName])
              ->andFilterWhere(['like', 'oblastOkrugName', $this->oblastOkrugName])
              ->andFilterWhere(['like', 'regionName', $this->regionName])
              ->andFilterWhere(['like', 'subject', $this->subject])
              ->andFilterWhere(['like', 'category', $this->category])
              ->andFilterWhere(['like', 'brand', $this->brand])
              ->andFilterWhere(['like', 'date', $this->date]);

        return $dataProvider;
    }
}