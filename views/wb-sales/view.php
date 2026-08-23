<?php
use yii\widgets\DetailView;

echo DetailView::widget([
    'model' => $model,
    'attributes' => [
        'saleID',
        'number',
        'date',
        'lastChangeDate',
        'supplierArticle',
        'nmId',
        'barcode',
        'category',
        'subject',
        'brand',
        'techSize',
        'totalPrice',
        'discountPercent',
        'forPay',
        'finishedPrice',
        'warehouseName',
        'countryName',
        'regionName',
    ],
]);