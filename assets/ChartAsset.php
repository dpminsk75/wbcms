<?php
namespace app\assets;

use yii\web\AssetBundle;

class ChartAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'https://cdn.amcharts.com/lib/5/index.js',
        'https://cdn.amcharts.com/lib/5/xy.js',
        'https://cdn.amcharts.com/lib/5/percent.js',
        'https://cdn.amcharts.com/lib/5/themes/Animated.js',
    ];
}
?>