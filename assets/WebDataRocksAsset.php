<?php
namespace app\assets;

use yii\web\AssetBundle;

class WebDataRocksAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'https://cdn.webdatarocks.com/latest/webdatarocks.min.css',
    ];
    public $js = [
        'https://cdn.webdatarocks.com/latest/webdatarocks.toolbar.min.js',
        'https://cdn.webdatarocks.com/latest/webdatarocks.js',
    ];
}