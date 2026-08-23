<?php

namespace app\models\scopes;

use Yii;
use yii\db\ActiveQuery;

class CompanyActiveQuery extends ActiveQuery
{
    /**
     * Этот метод вызывается Yii прямо перед выполнением SQL-запроса (all, one, count и т.д.)
     */
    public function init()
    {
        parent::init();
/*
        $companyId = Yii::$app->companyManager->getCurrentId();
        
        // ДОБАВЬ ЭТУ ПРОВЕРКУ:
        if ($companyId !== null) {
            $this->andWhere(['company_id' => $companyId]);
        }
        // Если $companyId === null, значит мы в "глобальном режиме" и фильтр не накладываем!
*/
    }

    public function beforePrepare()
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        
        // Фильтруем только если ID компании известен/задан 
        if ($companyId !== null) {
/*
            $modelClass = $this->modelClass;
            $tableName = $modelClass::tableName();
            
            // Проверяем, чтобы условие не добавилось повторно
            $this->andWhere([$tableName . '.company_id' => $companyId]);
*/
            $alias = $this->getQueryTableAlias();
            $this->andWhere([$alias . '.company_id' => $companyId]);
        }

        return parent::beforePrepare();
    }
    
    protected function getQueryTableAlias()
    {
        if (!empty($this->from) && is_array($this->from)) {
            foreach ($this->from as $key => $value) {
                if (is_string($key)) {
                    // алиас указан как ключ массива, например ['s' => 'wb_sales']
                    return $key;
                }
            }
        }

        $modelClass = $this->modelClass;
        return $modelClass::tableName();
    }

    public function withoutCompanyScope()
    {
        // Удаляем условия, связанные с company_id из текущего запроса
        $this->where = null; 
        // Или более тонкая настройка: перебор условий и удаление нужного
        return $this;
    }
}