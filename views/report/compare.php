<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = "Сравнение продаж: $baseYear vs $compYear";

$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
    7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
];
?>

<div class="report-compare">
    <div class="box box-solid">
        <div class="box-body">
            <?= Html::beginForm(['report/compare'], 'get', ['class' => 'form-inline']) ?>
                <div class="row">
                    <div class="form-group col-md-2">
                        <label>Год отчета:</label>
                        <?= Html::dropDownList('baseYear', $baseYear, array_combine($availableYears, $availableYears), ['class' => 'form-control']) ?>
                    </div>

                    <div class="form-group col-md-2" >
                        <label>Сравнить с:</label>
                        <?= Html::dropDownList('compYear', $compYear, array_combine($availableYears, $availableYears), ['class' => 'form-control']) ?>
                    </div>

                    <div class="form-group col-md-4" >
                        <label>Метрика:</label>
                        <?= Html::dropDownList('metric', $currentMetric, $metrics, ['class' => 'form-control']) ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                    <i class="fa fa-refresh"></i> Обновить
                </button>

            <?= Html::endForm() ?>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Результаты сравнения (<?= $metrics[$currentMetric] ?>)</h3>
        </div>
        <div class="box-body no-padding">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr style="background: #f4f4f4;">
                        <th style="width: 150px;">Месяц</th>
                        <th class="text-center"><?= $compYear ?> (₽)</th>
                        <th class="text-center"><?= $baseYear ?> (₽)</th>
                        <th class="text-center">Абс. разница</th>
                        <th class="text-center">Динамика</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalBase = 0; 
                    $totalComp = 0; 

                    foreach ($report as $m => $years): 
                        $valBase = $years[$baseYear][$currentMetric];
                        $valComp = $years[$compYear][$currentMetric];
                        
                        $diff = $valBase - $valComp;
                        $perc = ($valComp != 0) ? ($diff / abs($valComp)) * 100 : ($valBase > 0 ? 100 : 0);
                        
                        $totalBase += $valBase;
                        $totalComp += $valComp;

                        $colorClass = $diff >= 0 ? 'text-green' : 'text-red';
                        $labelClass = $diff >= 0 ? 'label-success' : 'label-danger';
                    ?>
                        <tr>
                            <td><?= $monthNames[$m] ?></td>
                            <td class="text-right text-muted"><?= number_format($valComp, 2, '.', ' ') ?></td>
                            <td class="text-right" ><?= number_format($valBase, 2, '.', ' ') ?></td>
                            <td class="text-right <?= $colorClass ?>">
                                <strong><?= ($diff > 0 ? '+' : '') . number_format($diff, 2, '.', ' ') ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="label <?= $labelClass ?>" style="font-size: 0.9em;">
                                    <?= ($perc > 0 ? '+' : '') . round($perc, 1) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f9f9f9; font-weight: bold; border-top: 2px solid #ddd;">
                    <tr>
                        <td>ИТОГО</td>
                        <td class="text-right"><?= number_format($totalComp, 2, '.', ' ') ?></td>
                        <td class="text-right"><?= number_format($totalBase, 2, '.', ' ') ?></td>
                        <?php 
                            $totalDiff = $totalBase - $totalComp;
                            $totalPerc = ($totalComp != 0) ? ($totalDiff / abs($totalComp)) * 100 : 0;
                        ?>
                        <td class="text-right <?= $totalDiff >= 0 ? 'text-green' : 'text-red' ?>">
                            <?= ($totalDiff > 0 ? '+' : '') . number_format($totalDiff, 2, '.', ' ') ?>
                        </td>
                        <td class="text-center">
                             <span class="badge <?= $totalDiff >= 0 ? 'bg-green' : 'bg-red' ?>">
                                <?= ($totalPerc > 0 ? '+' : '') . round($totalPerc, 1) ?>%
                             </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>  