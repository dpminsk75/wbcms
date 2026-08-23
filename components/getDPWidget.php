<?php
namespace app\components;

use Yii;
use yii\base\Widget;
use app\models\DPFilterForm;
use app\models\Product;
use app\models\WbCard;

class getDPWidget extends Widget
{
	public $action = ['']; 
	public $quickButtons = [];
    public $defaultDateFrom; 
    public $defaultDateTo; 
    public $defaultDays = 15;

/*
    public function run()
    {
        $model = new DPFilterForm();
        $model->load(Yii::$app->request->get());

        if (!$model->date_from) $model->date_from = date('Y-m-d', strtotime('-15 days')); 
        if (!$model->date_to)   $model->date_to   = date('Y-m-d');    
        $cardsList = WbCard::getListForSelect();
//        $products = WbCard::find()->select(['title', 'nmID', 'vendorCode'])->indexBy('id')->column();

        return $this->render('dp-view', [
            'model' => $model,
            'cardsList' => $cardsList,
            'action' => $this->action,
            'quickButtons' => $this->quickButtons,
        ]);
    }
*/

    public function run($days = 15)
    {
        $model = new DPFilterForm();
        $model->load(Yii::$app->request->get());

        if (!$model->date_from) {
//            $model->date_from = $this->defaultDateFrom ?? date('Y-m-d', strtotime('-$days days'));
            $model->date_from = $this->defaultDateFrom ?? date('Y-m-d', strtotime("-{$days} days"));
        }

        if (!$model->date_to) {
            $model->date_to = $this->defaultDateTo ?? date('Y-m-d', strtotime('-1 day'));
        }
        
        $cardsList = WbCard::getListForSelect();

        return $this->render('dp-view', [
            'model' => $model,
            'cardsList' => $cardsList,
            'action' => $this->action,
            'quickButtons' => $this->quickButtons,
        ]);
    }

    /**
     * Статический хелпер для получения данных в контроллере
     */
    public static function getParams($defaultDays = 15)
    {
        $model = new DPFilterForm();
        $model->load(Yii::$app->request->get());

        if (!$model->date_from) {
            $model->date_from = date('Y-m-d', strtotime("-{$defaultDays} days"));
        }
        
        if (!$model->date_to) {
//            $model->date_to = date('Y-m-d');
            $model->date_to = date('Y-m-d', strtotime('-1 day'));
        }

        return [
            'nm_id'     => $model->nm_id,
            'date_from' => $model->date_from,
            'date_to'   => $model->date_to,
        ];
    }
}
