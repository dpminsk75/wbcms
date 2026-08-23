<?php
namespace app\components;

use Yii;
use yii\base\Widget;
use app\models\DPFilterForm;

class UniversalFilterWidget extends Widget
{
    public $action = ['']; 
    public $quickButtons = [];
    public $data = [];           
    public $attribute = 'nmID'; 
    public $label = 'Выбор';      
    public $placeholder = 'Выберите...';
    public $pluginOptions = []; 
    
    // Параметры для AJAX
    public $ajaxUrl = null; 
    public $initValueText = ''; 
    
    public $defaultDays = 30;
    public $defaultDateFrom; 
    public $defaultDateTo; 

    public function run()
    {
        $model = new DPFilterForm();
        $model->load(Yii::$app->request->get());

//        var_dump($model);
/*
        if ($attribute = 'phrase_text') {
            if ($model->phrase_text) {
                // Если всё же пришел чистый ID (выбрали из подсказок)
                $$model->phrase_text = \app\models\WbPhrasesDirectory::findOne($model->phrase_text);
            } else {
                // Если пришел текст "лунный" — ищем все фразы, где он есть
            }
        }
*/
        if ($this->attribute === 'phrase_text') { // Используем === для сравнения, а не =
            if (!empty($model->phrase_text)) {
                // Проверяем, является ли значение ID (числом)
                if (is_numeric($model->phrase_text)) {
                    $phraseRecord = \app\models\WbPhrasesDirectory::findOne($model->phrase_text);
                    if ($phraseRecord) {
                        // Подменяем ID на реальный текст из базы
                        $model->phrase_text = $phraseRecord->phrase; // или имя поля с текстом
                    }
                } else {
                    // Если пришел не ID, а уже текст (например, "лунный")
                    // Здесь можно оставить как есть или выполнить поиск
                }
            }
        }

        $defaultSelect2Options = [
            'allowClear' => true,
            'width' => '100%',
        ];
        
        $this->pluginOptions = array_merge($defaultSelect2Options, $this->pluginOptions);

        if (!$model->date_from) {
            $model->date_from = $this->defaultDateFrom ?? date('Y-m-d', strtotime("-{$this->defaultDays} days"));
        }
        if (!$model->date_to) {
            $model->date_to = $this->defaultDateTo ?? date('Y-m-d', strtotime('-1 day'));
        }

        return $this->render('universal-view', [
            'model'         => $model,
            'attribute'     => $this->attribute,
            'data'          => $this->data,
            'label'         => $this->label,
            'action'        => $this->action,
            'placeholder'   => $this->placeholder,
            'quickButtons'  => $this->quickButtons,
            'pluginOptions' => $this->pluginOptions,
            'ajaxUrl'       => $this->ajaxUrl,
            'initValueText' => $this->initValueText,
        ]);
    }

    public static function getParams($attribute = 'nmID', $defaultDays = 30)
    {
        $model = new \app\models\DPFilterForm();
        $model->load(Yii::$app->request->get());

        if (!$model->date_from) {
            $model->date_from = date('Y-m-d', strtotime("-{$defaultDays} days"));
        }
        if (!$model->date_to) {
            $model->date_to = date('Y-m-d', strtotime('-1 day'));
        }

        return [
            'id'        => $model->$attribute,
            'date_from' => $model->date_from,
            'date_to'   => $model->date_to,
        ];
    }
}