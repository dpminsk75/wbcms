<?php

namespace app\components\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

class CompanyBehavior extends Behavior
{
    public $attribute = 'company_id';

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    public function beforeValidate($event)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        
        // Если у модели есть атрибут company_id и он еще не заполнен вручную
        if ($companyId !== null && $this->owner->hasAttribute($this->attribute) && empty($this->owner->{$this->attribute})) {
            $this->owner->{$this->attribute} = $companyId;
        }
    }
}