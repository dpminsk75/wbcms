<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\data\ActiveDataProvider;

class DetailByPeriod extends ActiveRecord
{
    use CompanyScopedTrait;
    // Свойства для агрегации и связей
    public $card_name;
    public $to_client_cancel;
    public $from_client_cancel;
    public $from_client_return;
    public $defect_return;
    public $other_sum;
    public $total_product_sum;
    public $items_count;
    public $total_sum;
    public $date_only;

    public static function tableName() { return 'detail_by_period'; }

    public function rules()
    {
        return [
            [['rrd_id'], 'required'],
            [['nm_id'], 'integer'],
            [['total_product_sum', 'to_client_cancel', 'from_client_cancel', 'from_client_return', 'defect_return', 'other_sum', 'total_sum', 'items_count'], 'safe'],
            [['rr_dt', 'bonus_type_name', 'office_name'], 'safe'], // Добавлен office_name
        ];
    }

    public function getSummaryByPeriod($dateFrom, $dateTo)
    {
        return self::find()
            ->select([
                'bonus_type_name',
                'items_count' => 'COUNT(DISTINCT srid)',
                'total_sum' => 'SUM(delivery_rub)'
            ])
            ->where(['supplier_oper_name' => 'Логистика'])
            ->andWhere(['or', ['doc_type_name' => ''], ['doc_type_name' => null]])
            ->andWhere(['not', ['bonus_type_name' => 'К клиенту при продаже']]) // Исключаем штатную логистику
            ->andWhere(['between', 'rr_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy('bonus_type_name')
            ->orderBy(['total_sum' => SORT_DESC])
            ->asArray()
            ->all();
    }

    public function getSummaryLogistics($dateFrom, $dateTo)
    {
        return self::find()
            ->select([
                'date_only' => 'DATE(rr_dt)',
                'bonus_type_name',
                'total_sum' => 'SUM(delivery_rub)',
                'items_count' => 'COUNT(DISTINCT srid)'
            ])
            ->where(['supplier_oper_name' => 'Логистика'])
            ->andWhere(['or', ['doc_type_name' => ''], ['doc_type_name' => null]])
            ->andWhere(['not', ['bonus_type_name' => 'К клиенту при продаже']]) // Исключаем штатную логистику
            ->andWhere(['between', 'rr_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(['date_only', 'bonus_type_name'])
            ->orderBy(['date_only' => SORT_DESC])
            ->asArray()
            ->all();
    }
/*
public function searchPenaltyLogistics($params)
{
    $query = self::find()->alias('t')
        ->select(['t.*', 'card_name' => 'c.title'])
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = t.nm_id')
        ->where(['t.supplier_oper_name' => 'Логистика'])
        ->andWhere(['or', ['t.doc_type_name' => ''], ['t.doc_type_name' => null]])
        ->andWhere(['not', ['t.bonus_type_name' => 'К клиенту при продаже']]);

    // Учитываем даты, если они пришли
    if (!empty($params['date_from']) && !empty($params['date_to'])) {
        $query->andWhere(['between', 't.rr_dt', $params['date_from'] . ' 00:00:00', $params['date_to'] . ' 23:59:59']);
    }

    $dataProvider = new ActiveDataProvider([
        'query' => $query,
        'pagination' => ['pageSize' => 50],
        'sort' => [
            'defaultOrder' => ['rr_dt' => SORT_DESC],
        ],
    ]);

    $this->load($params);

    if (!$this->validate()) {
        return $dataProvider;
    }

    // === ПРИМЕНЯЕМ ФИЛЬТРЫ ИЗ СТОЛБЦОВ GRIDVIEW ===
    $query->andFilterWhere([
        't.nm_id' => $this->nm_id,
    ]);

    $query->andFilterWhere(['like', 't.bonus_type_name', $this->bonus_type_name])
          ->andFilterWhere(['like', 't.office_name', $this->office_name]);

    return $dataProvider;
}
*/

public function searchPenaltyLogistics($params)
{
    $query = self::find()->alias('t')
        ->select(['t.*', 'card_name' => 'c.title'])
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = t.nm_id');

    $dataProvider = new ActiveDataProvider([
        'query' => $query,
        'sort' => ['defaultOrder' => ['rr_dt' => SORT_DESC]],
    ]);

    $this->load($params);

    if (!$this->validate()) {
        return $dataProvider;
    }

    // Фильтрация по выбранным в Select2 значениям
    $query->andFilterWhere(['t.nm_id' => $this->nm_id]);
    $query->andFilterWhere(['t.office_name' => $this->office_name]);
    $query->andFilterWhere(['t.bonus_type_name' => $this->bonus_type_name]);

    return $dataProvider;
}

}