<?php

namespace app\components;

use yii\base\BaseObject;

class WbImageHelper extends BaseObject
{
    /**
     * Генерирует URL изображения Wildberries по артикулу
     * 
     * @param int|string $article Артикул товара
     * @param int $number Номер фото (1, 2, 3...)
     * @param string $size Размер (big, small, tm, c246x328)
     * @return string
     */
    public static function getUrl($article, $number = 1, $size = 'big')
    {
        $vol = floor($article / 100000);
        $part = floor($article / 1000);
        
        $basket = self::getBasketNumber($vol);
        
        // Используем современный домен CDN
//        return "https://basket-{$basket}.wb.ru/vol{$vol}/part{$part}/{$article}/images/{$size}/{$number}.webp";
        return "https://mns-basket-cdn-02.geobasket.net/vol{$vol}/part{$part}/{$article}/images/{$size}/{$number}.webp";
//        mns-basket-cdn-02.geobasket.net
    }

    /**
     * Логика определения корзины на основе vol
     */
    private static function getBasketNumber($vol)
    {
        if ($vol >= 0 && $vol <= 143) return '01';
        if ($vol >= 144 && $vol <= 287) return '02';
        if ($vol >= 288 && $vol <= 431) return '03';
        if ($vol >= 432 && $vol <= 719) return '04';
        if ($vol >= 720 && $vol <= 1007) return '05';
        if ($vol >= 1008 && $vol <= 1061) return '06';
        if ($vol >= 1062 && $vol <= 1115) return '07';
        if ($vol >= 1116 && $vol <= 1169) return '08';
        if ($vol >= 1170 && $vol <= 1313) return '09';
        if ($vol >= 1314 && $vol <= 1601) return '10';
        if ($vol >= 1602 && $vol <= 1655) return '11';
        if ($vol >= 1656 && $vol <= 1919) return '12';
        if ($vol >= 1920 && $vol <= 2045) return '13';
        if ($vol >= 2046 && $vol <= 2189) return '14';
        if ($vol >= 2190 && $vol <= 2405) return '15';
        return '16';
    }
}
