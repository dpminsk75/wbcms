<?php
use yii\helpers\Html;
use kartik\select2\Select2;

$this->title = 'Еженедельный отчет: ' . ($productId ? $productsList[$productId] : 'Выберите товар');
?>
<div class="weekly-report">
    <h1>Детализация по неделям по товару</h1>

<div class="row" style="margin-bottom: 20px;">
    <div class="well col-md-6">
        <div class="div_bordered">
            <?= Html::beginForm(['weekly-report'], 'get', ['class' => 'form-inline form__sales-funnel']) ?>
                <div class="form-group" style="min-width: 400px; margin-right: 15px;">
                    <label>Товар: </label>
                    <?= Select2::widget([
                        'name' => 'productId',
                        'value' => $productId,
                        'data' => $productsList, // Здесь должны быть пары [product_id => Название_товара]
                        'options' => ['placeholder' => 'Введите название товара ...'],
                        'pluginOptions' => [
                            'allowClear' => true
                        ],
                    ]); ?>
                </div>

                <div class="form-group form__sales-funnel-dates">
                    <label>Период с </label>
                    <?= Html::input('date', 'dateFrom', $dateFrom, ['class' => 'form-control form__date']) ?>
                    <label> по </label>
                    <?= Html::input('date', 'dateTo', $dateTo, ['class' => 'form-control form__date', 'style' => 'margin-right: 10px;']) ?>
                </div>

                <div class="form-group form__sales-funnel-btn">
                    <?= Html::submitButton('Показать', ['class' => 'btn btn-primary btn_200px']) ?>
                    <?= Html::a('Сброс', ['index'], ['class' => 'btn btn-light btn_200px']) ?>
                </div>

            <?= Html::endForm() ?>
        </div>
    </div>
    <div class="col-md-6">
        <?php if (!empty($relatedCards)): ?>
            <div class="alert alert-default div_bordered" style="background: #f1f1f1; border-left: 5px solid #337ab7; margin-bottom: 20px;">
                <strong>В состав товара входят карточки:</strong><br>
                <div style="margin-top: 10px;"><ul class="related_list">
                    <?php foreach ($relatedCards as $card): ?>
                        <li><a href="/wb-detail-by-period/weekly-report-nmid?nmId=<?=Html::encode($card['nmId'])?>&dateFrom=<?=Html::encode($dateFrom)?>&dateTo=<?=Html::encode($dateTo)?>" target="_blank">
                                <?= Html::encode($card['nmId']) ?></a> | <?= Html::encode($card['card_name'] ?: 'Без названия') ?> | <?= Html::encode($card['vendorCode']) ?> 
                        </li>
                    <?php endforeach; ?>
                </ul></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($data): ?>

<?php if ($productId && !empty($data)): ?>
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-6" id="LinechartDiv">
            <div class="panel panel-default div_bordered">
                <div class="panel-heading d-flex align-items-center" style="position: relative; justify-content: center; min-height: 40px;">
                    <b class="mx-auto">Продажи и Цена</b>
                    <a href="#" onclick="toggleWidth(); return false;" 
                       style="position: absolute; right: 15px; text-decoration: none; font-size: 14px;">
                       Изменить ширину
                    </a>
                </div>
                <div class="panel-body">
                    <div id="timeline_div" style="width: 100%; height: 500px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default div_bordered">
                <div class="panel-heading"><center><b>По регионам</b></center></div>
                <div class="panel-body">
                    <div id="donut_div" style="width: 100%; height: 500px;"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<?php 
    $weeklyDataByProduct[$productId] = [];
    foreach ($data as $row) {
            $weekKey = (string)$row['week_key'];
            $weeklyDataByProduct[$productId][$weekKey] = [
                'rows_count'           => (float)$row['rows_count'],
                'sales_count'          => (float)$row['sales_count'],
                'retail_price'         => (float)$row['retail_price'],
                'retail_amount'        => (float)$row['retail_amount'],
                'commission_percent'   => (float)$row['commission_percent'],
                'ppvz_spp_prc'         => (float)$row['ppvz_spp_prc'],
                'ppvz_sales_commission'=> (float)$row['ppvz_sales_commission'],
                'ppvz_reward'          => (float)$row['ppvz_reward'],
                'acquiring_fee'        => (float)$row['acquiring_fee'],
                'ppvz_vw'              => (float)$row['ppvz_vw'],
                'ppvz_vw_nds'          => (float)$row['ppvz_vw_nds'],
                'ppvz_for_pay'         => (float)$row['ppvz_for_pay'],
                'delivery_rub'         => (float)$row['delivery_rub'],
                'rebill_logistic_cost' => (float)$row['rebill_logistic_cost'],
            ];
    }
    $weeks = $weeklyDataByProduct[$productId] ?? [];
    $weekKeys = array_keys($weeks);
    sort($weekKeys, SORT_STRING);

    $fmt = function (?float $v, int $dec = 2): string {
        if ($v === null) {
            return '';
        }
        if (abs($v) < 0.0000001) {
            return '0';
        }
        return number_format($v, $dec, ',', ' ');
    };


?>
<?php
        echo '<h3>'.($productId ? $productsList[$productId] : '-').'</h3>';

            echo '<table class="table-scroll">';
            // Заголовок: первая строка — названия недель
            echo '<thead><tr><th class="sticky-col">Показатель \\ Неделя</th><th>Итого</th>';
            foreach ($weekKeys as $wk) {
                echo '<th>' . htmlspecialchars($wk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';

            // Строки показателей (суммарные значения за неделю)
            $rowDefs = [
                'rows_count'            => ['label' => 'Кол-во записей',              'dec' => 0],
                'sales_count'           => ['label' => 'Кол-во товаров, шт',              'dec' => 0],

                'retail_price'          => ['label' => 'Сумма розн., руб',         'dec' => 2],
                'retail_amount'         => ['label' => 'Сумма продажи, руб',       'dec' => 2],
                'commission_percent'    => ['label' => 'Комиссия WB, %',          'dec' => 2],
                'ppvz_spp_prc'          => ['label' => 'Скидка ПП, %',            'dec' => 2],
                'ppvz_sales_commission' => ['label' => 'Итог. скидка, руб',       'dec' => 2],
                'ppvz_reward'           => ['label' => 'Услуги ППВЗ, руб',        'dec' => 2],
                'acquiring_fee'         => ['label' => 'Эквайринг, руб',          'dec' => 2],
                'ppvz_vw'               => ['label' => 'Возн ВБ без НДС, руб',    'dec' => 2],
                'ppvz_vw_nds'           => ['label' => 'НДС, руб',                'dec' => 2],
                'ppvz_for_pay'          => ['label' => 'К перечислению, руб',     'dec' => 2],
                'delivery_rub'          => ['label' => 'Логистика, руб',          'dec' => 2],
                'rebill_logistic_cost'  => ['label' => 'Возвраты, руб',           'dec' => 2],
                // Итог: рассчитываем на лету
                '_total'                => ['label' => 'Итого, руб',              'dec' => 2],
            ];

            // Подсчет итогов для первой таблицы
            $totals = [
                'rows_count'            => 0.0,
                'sales_count'           => 0.0,
                'retail_price'          => 0.0,
                'retail_amount'         => 0.0,
                'commission_percent'    => 0.0, // будет взвешенное среднее
                'ppvz_spp_prc'          => 0.0, // будет взвешенное среднее
                'ppvz_sales_commission' => 0.0,
                'ppvz_reward'           => 0.0,
                'acquiring_fee'         => 0.0,
                'ppvz_vw'               => 0.0,
                'ppvz_vw_nds'           => 0.0,
                'ppvz_for_pay'          => 0.0,
                'delivery_rub'          => 0.0,
                'rebill_logistic_cost'  => 0.0,
                '_total'                => 0.0,
            ];
            $totalCount = 0.0; // для взвешенного среднего процентов
            $totalCommissionSum = 0.0;
            $totalSppSum = 0.0;

            foreach ($rowDefs as $key => $meta) {
                $totalCount = 0.0;
                echo '<tr>';
                echo '<td class="metric-name sticky-col">' . htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                
                // Сначала собираем данные по неделям для подсчета итогов
                $weekValues = [];
                foreach ($weekKeys as $wk) {
                    $w = $weeks[$wk];
                    $val = null;
                    if ($key === '_total') {
                        $val = $w['ppvz_for_pay'] - $w['delivery_rub'] - $w['rebill_logistic_cost'];
                    } else {
                        $val = $w[$key] ?? null;
                    }
                    $weekValues[$wk] = $val;
                    
                    // Подсчет итогов
                    if ($val !== null) {
                        if ($key === 'commission_percent' || $key === 'ppvz_spp_prc') {
                            // Для процентов считаем взвешенное среднее
                            $cnt = $w['rows_count'] ?? 0.0;
                            if ($cnt > 0) {
                                if ($key === 'commission_percent') {
                                    $totalCommissionSum += $val * $cnt;
                                } else {
                                    $totalSppSum += $val * $cnt;
                                }
                                $totalCount += $cnt;
                            }
                        } else {
                            $totals[$key] += $val;
                        }
                    }
                }
                
                // Вывод итоговой колонки (вторая колонка)
                $totalVal = null;
                if ($key === '_total') {
                    $totalVal = $totals['ppvz_for_pay'] - $totals['delivery_rub'] - $totals['rebill_logistic_cost'];
                } elseif ($key === 'commission_percent') {
                    $totalVal = $totalCount > 0 ? ($totalCommissionSum / $totalCount) : null;
                } elseif ($key === 'ppvz_spp_prc') {
                    $totalVal = $totalCount > 0 ? ($totalSppSum / $totalCount) : null;
                } else {
                    $totalVal = $totals[$key];
                }
                echo '<td><strong>' . $fmt($totalVal, $meta['dec']) . '</strong></td>';
                
                // Затем выводим значения по неделям
                foreach ($weekKeys as $wk) {
                    $val = $weekValues[$wk];
                    echo '<td>' . $fmt($val, $meta['dec']) . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Таблица «на 1 продажу»

            $rowDefs = [
                'rows_count'            => ['label' => 'Кол-во записей',          'dec' => 0],
                'sales_count'           => ['label' => 'Кол-во товаров, шт',      'dec' => 0],

                'retail_price'          => ['label' => 'Цена розн., руб',         'dec' => 2],
                'retail_amount'         => ['label' => 'Цена продажи, руб',       'dec' => 2],
                'commission_percent'    => ['label' => 'Комиссия WB, %',          'dec' => 2],
                'ppvz_spp_prc'          => ['label' => 'Скидка ПП, %',            'dec' => 2],
                'ppvz_sales_commission' => ['label' => 'Итог. скидка, руб',       'dec' => 2],
                'ppvz_reward'           => ['label' => 'Услуги ППВЗ, руб',        'dec' => 2],
                'acquiring_fee'         => ['label' => 'Эквайринг, руб',          'dec' => 2],
                'ppvz_vw'               => ['label' => 'Возн ВБ без НДС, руб',    'dec' => 2],
                'ppvz_vw_nds'           => ['label' => 'НДС, руб',                'dec' => 2],
                'ppvz_for_pay'          => ['label' => 'К перечислению, руб',     'dec' => 2],
                'delivery_rub'          => ['label' => 'Логистика, руб',          'dec' => 2],
                'rebill_logistic_cost'  => ['label' => 'Возвраты, руб',           'dec' => 2],
                // Итог: рассчитываем на лету
                '_total'                => ['label' => 'Итого, руб',              'dec' => 2],
            ];


            echo '<h3 class="product-header">На 1 экз.</h3>';
            echo '<table class="result_one table-scroll" >';
            echo '<thead><tr><th class="sticky-col">Показатель \\ Неделя</th><th>Итого</th>';
            foreach ($weekKeys as $wk) {
                echo '<th>' . htmlspecialchars($wk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';

            // Подсчет итогов для второй таблицы (на 1 продажу)
            $totalsPerUnit = [
                'rows_count'            => 0.0, // сумма
                'sales_count'           => 0.0, // сумма
                'retail_price'          => 0.0, // среднее
                'retail_amount'         => 0.0, // среднее
                'commission_percent'    => 0.0, // среднее
                'ppvz_spp_prc'          => 0.0, // среднее
                'ppvz_sales_commission' => 0.0, // среднее
                'ppvz_reward'           => 0.0, // среднее
                'acquiring_fee'         => 0.0, // среднее
                'ppvz_vw'               => 0.0, // среднее
                'ppvz_vw_nds'           => 0.0, // среднее
                'ppvz_for_pay'          => 0.0, // среднее
                'delivery_rub'          => 0.0, // среднее
                'rebill_logistic_cost'  => 0.0, // среднее
                '_total'                => 0.0, // среднее
            ];
            $weekCount = 0; // количество недель с данными для среднего

            foreach ($rowDefs as $key => $meta) {
                echo '<tr>';
                echo '<td class="metric-name sticky-col">' . htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                
                // Сначала собираем данные по неделям для подсчета итогов
                $weekCount = 0;
                $weekValues = [];
                foreach ($weekKeys as $wk) {
                    $w = $weeks[$wk];
                    $cnt = isset($w['sales_count']) ? (float)$w['sales_count'] : 0.0;

                    $val = null;
                    if ($key === 'rows_count' || $key === 'sales_count')  {
                        // Для строки «Кол-во» оставляем фактическое количество продаж
                        $val = $w[$key];
                        $totalsPerUnit[$key] += $w[$key]; // сумма
                    } elseif ($key === 'commission_percent' || $key === 'ppvz_spp_prc') {
                        // Процентные поля уже усреднены — показываем как есть
                        $val = $w[$key] ?? null;
                        if ($val !== null) {
                            $totalsPerUnit[$key] += $val;
                            $weekCount++;
                        }
                    } else {
                        if ($cnt > 0.0000001) {
                            if ($key === '_total') {
                                $val = ($w['ppvz_for_pay'] - $w['delivery_rub'] - $w['rebill_logistic_cost']) / $cnt;
                            } else {
                                $raw = $w[$key] ?? null;
                                $val = $raw === null ? null : ($raw / $cnt);
                            }
                            if ($val !== null) {
                                $totalsPerUnit[$key] += $val;
                                $weekCount++;
                            }
                        } else {
                            $val = null;
                        }
                    }
                    $weekValues[$wk] = $val;
                }
                
                // Вывод итоговой колонки (вторая колонка)
                $totalVal = null;
                if ($key === 'rows_count' || $key === 'sales_count') {
                    // Для количества - сумма
                    $totalVal = $totalsPerUnit[$key];
                } else {
                    // Для остальных - среднее
                    $totalVal = $weekCount > 0 ? ($totalsPerUnit[$key] / $weekCount) : null;
                }
                echo '<td><strong>' . $fmt($totalVal, $meta['dec']) . '</strong></td>';
                
                // Затем выводим значения по неделям
                foreach ($weekKeys as $wk) {
                    $val = $weekValues[$wk];
                    echo '<td>' . $fmt($val, $meta['dec']) . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';

?>


<?php foreach ($groupedNmId as $nmID => $weeks): ?>
    <section class="nm-group">

    <?php 
        // Берем первую неделю, а в ней — первую запись
        $firstWeek = reset($weeks); 
        $firstItem = reset($firstWeek);
        $title = $firstItem['title'] ?? 'Без названия';
        $vendorCode = $firstItem['vendorCode'] ?? 'Без названия';

        echo '<h3>'.$title.' ('.$vendorCode.')</h3>';
        echo 'Арт. WB: <a href="https://www.wildberries.ru/catalog/'.htmlspecialchars((string)$nmID, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'/detail.aspx?targetUrl=EX" target="_blank">'.htmlspecialchars((string)$nmID, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'</a><br/>';

    ?>
<?php
            echo '<table class="table-scroll table_divs">';
            echo '<thead><tr><th class="sticky-col">Показатель \\ Неделя</th><th>Итого</th>';
            foreach ($allWeeks as $week) {
                echo '<th>' . htmlspecialchars($week, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';


            $rowDefs = [
                'rows_count'            => ['label' => 'Кол-во, шт',              'dec' => 0],
                'retail_price'          => ['label' => 'Сумма розн., руб',         'dec' => 2],
                'retail_amount'         => ['label' => 'Сумма продажи, руб',       'dec' => 2],
                'commission_percent'    => ['label' => 'Комиссия WB, %',          'dec' => 2],
                'ppvz_spp_prc'          => ['label' => 'Скидка ПП, %',            'dec' => 2],
                'ppvz_sales_commission' => ['label' => 'Итог. скидка, руб',       'dec' => 2],
                'ppvz_reward'           => ['label' => 'Услуги ППВЗ, руб',        'dec' => 2],
                'acquiring_fee'         => ['label' => 'Эквайринг, руб',          'dec' => 2],
                'ppvz_vw'               => ['label' => 'Возн ВБ без НДС, руб',    'dec' => 2],
                'ppvz_vw_nds'           => ['label' => 'НДС, руб',                'dec' => 2],
                'ppvz_for_pay'          => ['label' => 'К перечислению, руб',     'dec' => 2],
                'delivery_rub'          => ['label' => 'Логистика, руб',          'dec' => 2],
                'rebill_logistic_cost'  => ['label' => 'Возвраты, руб',           'dec' => 2],
                // Итог: рассчитываем на лету
                '_total'                => ['label' => 'Итого, руб',              'dec' => 2],
            ];
?>
<?php
        echo '<tr>';
                echo '<td class="sticky-col"><div class="td_column">';
                echo '<div class="td_value">'.'Кол-во, шт         '.'</div>';
                echo '<div class="td_value">'.'Цена розн., руб    '.'</div>';
                echo '<div class="td_value">'.'Цена продажи, руб  '.'</div>';
                echo '<div class="td_value td_yellow">'.'Цена за 1 шт, руб  '.'</div>';
                echo '<div class="td_value">'.'Комиссия WB, %     '.'</div>';
                echo '<div class="td_value">'.'Скидка ПП, %       '.'</div>';
                echo '<div class="td_value">'.'К перечислению, руб'.'</div>';
                echo '<div class="td_value">'.'Логистика, руб     '.'</div>';
                echo '<div class="td_value">'.'Итого, руб         '.'</div>';
                echo '<div class="td_value td_yellow">'.'За 1 шт, руб         '.'</div>';
                echo '<div class="td_value">'.''.'</div>';
                echo '<div class="td_value td_green">'.'Кол-во, шт         '.'</div>';
                echo '<div class="td_value td_green">'.'Цена розн., руб    '.'</div>';
                echo '<div class="td_value td_green">'.'Цена продажи, руб  '.'</div>';
                echo '<div class="td_value td_dgreen">'.'Цена за 1 шт, руб  '.'</div>';
                echo '<div class="td_value td_green">'.'К перечислению, руб'.'</div>';
                echo '<div class="td_value td_green">'.'Логистика, руб     '.'</div>';
                echo '<div class="td_value td_dgreen">'.'За 1 шт, руб         '.'</div>';
                echo '</div></td><td><div class="td_value"></div></td>';

//    foreach ($weeks as $weekKey => $items) {
    foreach ($allWeeks as $week) { 
        if (isset($weeks[$week])) {
            foreach ($weeks[$week] as $row)  {
//            foreach ($items as $row) {
                echo '<td><div class="td_column">';
//                echo '<div class="td_value">'.$week.'</div>';
//                echo '<div class="td_value">'.$row['week_key'].'</div>';

                echo '<div class="td_value">'.$fmt($row['rows_count'],0).'</div>';
                echo '<div class="td_value">'.$fmt($row['retail_price_full'],2).'</div>';
                echo '<div class="td_value">'.$fmt($row['retail_amount_full'],2).'</div>';

                echo '<div class="td_value td_yellow">'.($row['rows_count'] > 0 ? $fmt($row['retail_amount_full']/$row['rows_count'],2) : '-').'</div>';

                echo '<div class="td_value">'.$fmt($row['commission_percent'],2).'</div>';
                echo '<div class="td_value">'.$fmt($row['ppvz_spp_prc'],2).'</div>';
                echo '<div class="td_value">'.$fmt($row['ppvz_for_pay_full'],2).'</div>';
                echo '<div class="td_value">'.$fmt($row['delivery_rub_full'],2).'</div>';
                echo '<div class="td_value">'.$fmt($row['ppvz_for_pay_full'] - $row['delivery_rub_full'],2).'</div>';
                echo '<div class="td_value td_yellow">'.($row['rows_count'] > 0 ? $fmt(($row['ppvz_for_pay_full'] - $row['delivery_rub_full'])/$row['rows_count'],2) : '-').'</div>';

                echo '<div class="td_value">'.''.'</div>'; 
                echo '<div class="td_value td_green">'.$fmt($row['sales_count'],0).'</div>';
                echo '<div class="td_value td_green">'.$fmt($row['retail_price'],2).'</div>';
                echo '<div class="td_value td_green">'.$fmt($row['retail_amount'],2).'</div>';
                echo '<div class="td_value td_dgreen">'.($row['sales_count'] > 0 ? $fmt($row['retail_amount']/$row['sales_count'],2) : '-').'</div>';
                echo '<div class="td_value td_green">'.$fmt($row['ppvz_for_pay'],2).'</div>';
                echo '<div class="td_value td_green">'.$fmt($row['delivery_rub'],2).'</div>';
                echo '<div class="td_value td_dgreen">'.($row['sales_count'] > 0 ? $fmt(($row['ppvz_for_pay']-$row['delivery_rub'])/$row['sales_count'],2) : '-').'</div>';

                echo '</div></td>';
            }
        } else echo '<td><div class="td_value"></div></td>';
    }
    echo '</tr>';
?>
    </table>
</section>
<?php endforeach; ?>

<?php endif; ?>
</div>

<style>
/* from head  */

        body {
            font-family: "Open Sans", Arial, sans-serif;
/*            margin: 20px;
            background-color: #f7f9fc;*/
        }
        h1, h2 {
            color: #2d3748;
        }
        .filters {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .card-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            padding: 8px;
            background: #fff;
            min-width: 320px;
        }
        .card-item {
            display: flex;
            align-items: center;
            font-size: 13px;
            padding: 2px 0;
        }
        .card-item label {
            margin-left: 4px;
            cursor: pointer;
        }
        .search-input {
            width: 100%;
            padding: 6px 8px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #cbd5e0;
            font-size: 13px;
        }
        .date-block label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .date-block input[type="date"] {
            padding: 4px 6px;
            font-size: 14px;
        }
        .date-block input[type="text"] {
            width: 80px;
            padding: 4px 6px;
            font-size: 14px;
            text-align: right;
        }
        button {
            padding: 8px 16px;
            font-size: 14px;
            background-color: #3182ce;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2b6cb0;
        }
        .metric-name {
            font-weight: bold;
        }
        .product-header {
            margin-top: 30px;
            margin-bottom: 5px;
        }
        .no-data {
            margin-top: 10px;
            color: #718096;
        }

/* from bottom */

    .date-block {
        height: 50px;
        display: flex;
        flex-direction: row;
        align-items: center;
    }
    .date-block label {
        width: 180px;
    }
    .date-block__button {

    }
    .filters {
        width: 980px;
    }
    .search-input {
        width: 960px;
        padding: 8px;
        margin: 10px auto;
    }
    .result_one tbody tr:nth-child(2), .result_one tbody tr:nth-child(4), .result_one tbody tr:nth-child(12) {
        background-color: #fef7cd; /* Желтый цвет фона */
    }
    table {
        width: max-content; /* таблица занимает реальную ширину контента */ 
    }
    .result_one {
        overflow-x: auto;
        max-width: 100%;
        border-collapse: collapse; 
    }

    .table-scroll { 
        overflow-x: auto; 
        overflow-y: hidden; /* чтобы не было двойного вертикального скролла */ 
/*        max-width: 95vw; */ /* ограничение реальной шириной окна */ 
        width: 100%;           /* Занимать всю ширину родителя */
        max-width: 100%;       /* Не выходить за границы родителя */
        -webkit-overflow-scrolling: touch; /* плавный скролл на iOS */ 
        min-width: 0;
    }
        table {
            display: block;
            border-collapse: collapse;
            margin: 0px;
            background: #fff;
            white-space: nowrap;    
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 12px;
            text-align: right;
            min-width: 72px;
        }
        th:first-child, td:first-child {
            text-align: left;
            white-space: nowrap;
        }
        thead {
            background: #cde0f0;
        }
        .sticky-col { 
            position: sticky; left: 0; 
            background: #fff; /* фон обязателен, иначе текст будет накладываться */ 
            z-index: 2; /* чтобы колонка была поверх других ячеек */ 
            background: #cde0f0;
        }
        .card_title {
            font-family: "Istok Web", sans-serif;
            font-weight: 400;
            font-style: normal;
        }
        .card_title h2 {
            font-weight: 700;
        }

.table_divs tr, .table_divs td {
    margin: 0px;
    padding: 0px;    
}
.td_column {
    margin: 0px;
    padding: 0px;    
}
.td_value {
    margin: 0px;
    padding: 3px 6px;
    min-height: 25px;
    min-width: 72px;
}
.td_yellow {
    background-color: #fef7cd;
}
.td_green {
    background-color: #aece82;
}
.td_dgreen {
    background-color: #7c9939;
}

.weekly-report h3 {
    font-size: 17px;
    font-weight: 600;
    color: #1976D2;
    margin-top: 20px;
}

h1 {
    font-size: 25px;
    font-weight: 700;
}
</style>

<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<?php if (!empty($chartTimelineData)) {
    $timelineJson = json_encode($chartTimelineData, JSON_NUMERIC_CHECK);
    echo $this->render('_linechart', [
        'timelineJson' => $timelineJson,
    ]);
}
?>
<?php if (!empty($chartRegionData)) {
    $regionJson = json_encode($chartRegionData, JSON_NUMERIC_CHECK);
    echo $this->render('_donutchart', [
        'regionJson' => $regionJson,
    ]);
}
?>

<script>
function toggleWidth() {
    const element = document.getElementById('LinechartDiv');
    // Переключаем оба класса одновременно
    element.classList.toggle('col-md-6');
    element.classList.toggle('col-md-12');
}
</script>