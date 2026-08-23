<?php

namespace app\components;

use Yii;
use app\models\WbCard;

/**
 * Подключается в модели с полем nm_id (StockMovement, StockSnapshot, ProductionPlanItem).
 * Проверяет, что товар принадлежит текущей активной компании (CompanyManager),
 * если не выбран режим "Все компании".
 *
 * Использование в rules():
 *   [['nm_id'], 'validateNmIdCompanyScope'],
 */
trait CompanyScopedNmIdTrait
{
    public function validateNmIdCompanyScope($attribute, $params)
    {
        if (!Yii::$app->has('companyManager')) {
            return; // компонент не зарегистрирован - фильтр не применяем
        }

        /** @var CompanyManager $manager */
        $manager = Yii::$app->companyManager;
        if ($manager->isGlobalMode()) {
            return;
        }

        $companyId = $manager->getCurrentId();
        if ($companyId === null) {
            return;
        }

        $card = WbCard::findOne($this->$attribute);
        if (!$card || (int)$card->company_id !== (int)$companyId) {
            $this->addError($attribute, 'Товар относится к другой компании.');
        }
    }
}
