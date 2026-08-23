<?php

namespace app\components;

use yii\base\Widget;
use yii\helpers\Html;

class PageHeaderWidget extends Widget
{
    public $title;
    public $nmId;

    public function run()
    {
        // Можно вынести верстку в отдельный файл (render), но для маленького куска допустимо и так
        return $this->render('page-header', [
            'title' => $this->title,
            'nmId' => $this->nmId,
        ]);
    }
}
