<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Стили и скрипты для страниц создания/редактирования тега
 * (drag & drop карточек Wildberries).
 */
class TagFormAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $css = [
        'css/tag-form.css',
    ];

    public $js = [
        'js/tag-form.js',
    ];

    public $depends = [
        JqueryAsset::class,
    ];
}