<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ProductSearch представляет модель поиска для товаров с поддержкой фильтрации по связанным nmID.
 */
class ProductSearch extends Product
{
    /**
     * @var string Виртуальное поле для фильтрации по nmID из связанной таблицы wb_card
     */
    public $nmIdFilter;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'product_type_id', 'brand_id'], 'integer'],
            [['name', 'vat_rate', 'nmIdFilter'], 'safe'],
            [['cost', 'weight'], 'number'],
        ];
    }

    /**
     * Создает экземпляр data provider с поисковым запросом.
     *
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        // Подгружаем связи. 
        // joinWith используем для фильтрации по связанным таблицам
        $query = Product::find()->joinWith(['productType', 'brand', 'wbCards']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes' => [
                    'id',
                    'name',
                    'cost',
                    'weight',
                    'product_type_id' => [
                        'asc' => ['product_type.name' => SORT_ASC],
                        'desc' => ['product_type.name' => SORT_DESC],
                    ],
                    'brand_id' => [
                        'asc' => ['brand.name' => SORT_ASC],
                        'desc' => ['brand.name' => SORT_DESC],
                    ],
                ]
            ]
        ]);

        // ГРУППИРОВКА: Предотвращает дублирование строк товара, 
        // если к нему привязано несколько nmID через JOIN
        $query->groupBy(['{{%product}}.id']);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Фильтрация по основным полям
        $query->andFilterWhere(['{{%product}}.id' => $this->id])
              ->andFilterWhere(['product_type_id' => $this->product_type_id])
              ->andFilterWhere(['brand_id' => $this->brand_id]);

        $query->andFilterWhere(['like', '{{%product}}.name', $this->name])
              ->andFilterWhere(['like', 'vat_rate', $this->vat_rate]);

        // Фильтрация по связанному nmID (Привязанный ID)
        // Используем псевдоним таблицы wb_card, чтобы избежать конфликтов
        if (!empty($this->nmIdFilter)) {
            $query->andFilterWhere(['like', '{{%wb_card}}.nmID', $this->nmIdFilter]);
        }

        return $dataProvider;
    }
}