<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Поисковая модель для списка карточек WB.
 */
class WbCardSearch extends WbCard
{
    public $onlyUnused = 0; // По умолчанию выключено
    public $pageSize = 20;  // Количество строк на странице по умолчанию

    public function rules(): array
    {
        return [
            // Разрешаем только целые числа для ID и размера страницы
            [['nmID', 'imtID', 'subjectID', 'pageSize'], 'integer'],
            [['nmUUID', 'subjectName', 'vendorCode', 'brand', 'title', 'description', 'onlyUnused'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = WbCard::find();

        // 1. Загружаем параметры ДО инициализации провайдера, чтобы свойства модели заполнились
        $this->load($params);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['nmID' => SORT_DESC],
            ],
            'pagination' => [
                // 2. Сразу динамически задаем размер страницы из модели
                'pageSize' => $this->pageSize ? (int)$this->pageSize : 20,
            ],
        ]);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        // Логика фильтрации: исключаем те nmID, которые есть в product_wb_card
        if ($this->onlyUnused) {
            $query->leftJoin('product_wb_card', 'product_wb_card.wb_nm_id = wbcards.nmID')
                  ->andWhere(['product_wb_card.wb_nm_id' => null]);
        }

        $query->andFilterWhere(['nmID' => $this->nmID]);
        $query->andFilterWhere(['imtID' => $this->imtID]);
        $query->andFilterWhere(['subjectID' => $this->subjectID]);

        $query->andFilterWhere(['like', 'nmUUID', $this->nmUUID]);
        $query->andFilterWhere(['like', 'subjectName', $this->subjectName]);
        $query->andFilterWhere(['like', 'vendorCode', $this->vendorCode]);
        $query->andFilterWhere(['like', 'brand', $this->brand]);
        $query->andFilterWhere(['like', 'title', $this->title]);
        $query->andFilterWhere(['like', 'description', $this->description]);

        return $dataProvider;
    }
}