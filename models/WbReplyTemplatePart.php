<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Модель для таблицы "wb_reply_template_parts"
 *
 * @property int $id
 * @property int $rule_id
 * @property string $part_type
 * @property string $text
 *
 * @property WbReplyRule $rule
 */
class WbReplyTemplatePart extends ActiveRecord
{
    const TYPE_GREETING = 'greeting';
    const TYPE_BODY = 'body';
    const TYPE_SIGNOFF = 'signoff';

    public static function tableName()
    {
        return 'wb_reply_template_parts';
    }

    public function rules()
    {
        return [
            [['part_type', 'text'], 'required'],
            [['rule_id'], 'integer'],
            [['part_type', 'text'], 'string'],
            [['rule_id'], 'exist', 'skipOnError' => true, 'targetClass' => WbReplyRule::class, 'targetAttribute' => ['rule_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'rule_id' => 'ID правила',
            'part_type' => 'Тип части',
            'text' => 'Текст шаблона',
        ];
    }

    /**
     * Связь с основным правилом
     */
    public function getRule()
    {
        return $this->hasOne(WbReplyRule::class, ['id' => 'rule_id']);
    }
}