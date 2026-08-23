<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $subject_id
 * @property string $subject_name
 * @property int $parent_id
 * @property string $parent_name
 */
class WbSubjectCatalog extends ActiveRecord
{
    public static function tableName()
    {
        return 'wb_subject_catalog';
    }

    public function rules()
    {
        return [
            [['subject_id', 'subject_name', 'parent_id', 'parent_name'], 'required'],
            [['subject_id', 'parent_id'], 'integer'],
            [['subject_name', 'parent_name'], 'string', 'max' => 255],
            [['subject_id'], 'unique'],
        ];
    }
}