<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Модель для таблицы "wb_phrases_directory"
 *
 * @property int $id
 * @property string $phrase
 * @property string $created_at
 */
class WbPhrasesDirectory extends ActiveRecord
{
    public static function tableName()
    {
        return 'wb_phrases_directory';
    }

    public function rules()
    {
        return [
            [['phrase'], 'required'],
            [['phrase'], 'string', 'max' => 500],
            [['phrase'], 'unique'],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'phrase' => 'Поисковая фраза',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Поиск по ID
     * @param int $id
     * @return self|null
     */
    public static function findById($id)
    {
        return self::findOne($id);
    }

    /**
     * Поиск по подстроке (LIKE)
     * @param string $q
     * @param int $limit
     * @return self[]
     */
    public static function findBySubstring($q, $limit = 20)
    {
        return self::find()
            ->where(['like', 'phrase', $q])
            ->limit($limit)
            ->all();
    }

    /**
     * Получение данных для Select2 (AJAX или статический список)
     * Формат: [['id' => 1, 'text' => 'фраза'], ...]
     * * @param string|null $q Строка поиска для AJAX
     * @return array
     */
    public static function getListForSelect2($q = null)
    {
        $query = self::find()
            ->select(['id', "CONCAT(phrase, ' (', max_frequency, ')') AS text"])
            ->orderBy(['max_frequency' => SORT_DESC]);

        if ($q !== null) {
            $query->andWhere(['like', 'phrase', $q]);
        }

        return $query->limit(100)->asArray()->all();
    }
    public static function searchForWidget($q)
    {
        return self::ajaxSearch($q);
    }

    public static function getTextForWidget($id)
    {
        return self::getAjaxText($id);
    }

        public static function ajaxSearch($q)
        {
            return self::find()
                ->select([
                    'id' => 'id', 
                    'text' => "CONCAT(phrase, ' (', max_frequency, ')')"    
                ])
                ->where(['like', 'phrase', $q])
                // Добавляем сортировку по убыванию частотности
                ->orderBy(['max_frequency' => SORT_DESC]) 
                ->limit(20)
                ->asArray()
                ->all();
        }
    public static function getAjaxText($id)
    {
        $model = self::findOne($id);
        return $model ? $model->phrase : '';
    }
}