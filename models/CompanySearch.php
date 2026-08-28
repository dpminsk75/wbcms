<?php
namespace app\models;

use yii\data\ActiveDataProvider;

class CompanySearch extends Company
{
    public function rules()
    {
        return [
            [['id', 'is_active', 'fbs_deduct_enabled', 'fbs_deduct_test'], 'integer'],
            [['name', 'abbreviation', 'inn'], 'safe'],
        ];
    }

    public function search($params)
    {
        $query = Company::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['id' => SORT_ASC]],
            'pagination' => ['pageSize' => 20],
        ]);
        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }
        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['is_active' => $this->is_active]);
        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['like', 'abbreviation', $this->abbreviation]);
        $query->andFilterWhere(['like', 'inn', $this->inn]);
        return $dataProvider;
    }
}
