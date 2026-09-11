<?php
use app\assets\AppAsset;
AppAsset::register($this);

use yii\helpers\Html;
use yii\helpers\Url;
use kartik\icons\Icon;
use kartik\grid\GridView;
use yii\bootstrap5\BootstrapIconAsset;
use yii\widgets\Menu;

Icon::map($this);
BootstrapIconAsset::register($this);

$this->title = 'Управление товарами и карточками';
$isFbsOnly = !Yii::$app->user->isGuest && Yii::$app->user->can('manageFbsStocks') && !Yii::$app->user->can('viewReports') && !Yii::$app->user->can('admin') && !Yii::$app->user->can('viewOrders');
$dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-3 days'));
$dateTo = $dateTo ?: date('Y-m-d');
?>
<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<div class="site-index">
    <div class="row" style="margin-bottom: 20px;">
        <div class="nav_div col-md-2">
            <div class="list-group">
            <?php 
                echo Menu::widget([
                    'options' => ['class' => 'side-menu-list'],
                    'items' => \app\components\MenuHelper::getMenuItems('side'),
                ]);
            ?>
            </div>
            <div class="sidebar_img">
                <img src="/_icons/logo_300px.png" alt="Товары для WB" style="vertical-align: middle;">
            </div>
        </div>
        <div class="dash_div col-md-10">
            <?php // Preload GridView + Pjax + resizableColumns assets so AJAX grids stretch correctly
            echo '<div style="display:none">'.GridView::widget(['dataProvider'=>new \yii\data\ArrayDataProvider(['allModels'=>[]]), 'columns'=>[['attribute'=>'_dummy']], 'krajeeDialogSettings'=>['overrideYiiConfirm'=>false], 'resizableColumns'=>true, 'pjax'=>false, 'toggleData'=>false, 'export'=>false, 'options'=>['style'=>'display:none']]).'</div>';
            \yii\widgets\Pjax::widget(['id'=>'dummy-pjax-preload','options'=>['style'=>'display:none']]);
            ?>

<?php if (!$isFbsOnly): ?>
<div class="mobile-hide-block">
<?php 
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    echo $this->render('include/_new_cards');
}
?>
<div id="slot-top-metrics" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-top-metrics', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) ?>">
    <div class="dashboard-loader text-center p-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Загрузка метрик 30 дней...</div>
</div>
</div>
<?php else: ?>
<div id="slot-top-metrics" class="dashboard-slot d-none" data-url="<?= Url::to(['/site/dashboard-top-metrics', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) ?>"></div>
<?php endif; ?>

<?php if (!$isFbsOnly): ?>
<div id="slot-adv" class="dashboard-slot mobile-hide-block" data-url="<?= Url::to(['/site/dashboard-adv', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) ?>">
    <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка рекламы...</div></div>
</div>
<?php endif; ?>

<div class="mobile-hide-block">
    <div id="slot-orders-summary" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-orders-summary']) ?>">
        <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка сводки заказов...</div></div>
    </div>
</div>

<?php if (!$isFbsOnly): ?>
<div id="slot-today-widget" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-today-widget']) ?>">
    <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка виджета Заказы/Продажи...</div></div>
</div>
<?php endif; ?>

<div id="slot-last-orders" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-last-orders', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) ?>">
    <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка заказов...</div></div>
</div>

<?php if (!$isFbsOnly): ?>
<div class="mobile-hide-block">
    <div id="slot-last-sales" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-last-sales', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) ?>">
        <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка продаж...</div></div>
    </div>
</div>
<?php endif; ?>

    </div>

<?php if (!$isFbsOnly): ?>
<div class="mobile-hide-block">
    <div id="slot-monthly" class="dashboard-slot" data-url="<?= Url::to(['/site/dashboard-monthly-finance']) ?><?= Yii::$app->request->get('refresh') == 1 ? '&refresh=1' : '' ?>">
        <div class="dashboard-loader text-center p-5 text-muted"><div class="spinner-border mb-2"></div><div>Загрузка финансовой аналитики...</div></div>
    </div>
</div>
<?php endif; ?>

    </div>
</div>

<style>
    .dashboard-slot { min-height: 80px; position: relative; }
    .dashboard-loader { background: #fff; border: 1px dashed #e2e0ec; border-radius: 12px; }
    .dashboard-slot.has-error { border: 1px solid #f5c6cb; border-radius: 12px; padding: 16px; background: #fff; }
    .grid_wbstat .table { font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden; display: block; border-collapse: collapse;}
    .dashboard-slot { width: 100%; }
    .dashboard-slot .grid_wbstat .table { display: table !important; width: 100% !important; min-width: 100% !important; table-layout: auto !important; }
    .dashboard-slot .grid_wbstat .kv-grid-table { width: 100% !important; }
    .grid_wbstat .table td, .grid_wbstat .table th { padding: 4px 4px !important; }
    .grid_wbstat .table th {text-align: center;}
    .grid_wbstat input { font-size: 12px; }
    .grid_wbstat .input-group-text {padding: 4px;}
    .grid_wbstat {margin-bottom: 20px;}
    .grid_wbstat a {text-decoration: none; }
    .expandable-container { max-height: 250px !important; overflow: hidden !important; position: relative; transition: max-height 0.5s ease-in-out; margin-bottom: 10px; display: block; }
    .expandable-container.is-expanded { max-height: 20000px !important; }
    .expandable-container.is-expanded::after { display: none !important; }
    .expandable-container::after { content: ""; position: absolute; bottom: 0; left: 0; width: 100%; height: 50px; background: linear-gradient(transparent, white); pointer-events: none; transition: opacity 0.3s; }
    .expand-btn-wrapper { text-align: center; margin-bottom: 30px; }
    @media (max-width: 767px) { .nav_div { display: none !important; } .dash_div { width: 100% !important; flex: 0 0 100%; max-width: 100%; } .mobile-hide-block { display: none !important; } .today-stats-widget .tsw-chart-wrap, .today-stats-widget .tsw-axis-caption { display: none !important; } .today-stats-widget #tswUpdatedAt { display: none !important; } }
</style>

<?php
$js = <<<JS
(function(){
  // stub Krajee globals so AJAX GridView inline JS doesn't throw if assets not yet loaded
  if(typeof window.krajeeYiiConfirm === 'undefined'){ window.krajeeYiiConfirm = function(m,c){ if(confirm(m) && typeof c==='function') c(); return false; }; }
  if(typeof window.KrajeeDialog === 'undefined'){ window.KrajeeDialog = function(){}; window.KrajeeDialog.prototype = {dialog:function(){}, confirm:function(m,c){ if(confirm(m) && c) c(); }}; }
  if(typeof window.krajeeDialog === 'undefined'){ window.krajeeDialog = window.KrajeeDialog; }
  function loadSlot(el){
    if(!el || !el.dataset.url) return;
    el.dataset.loading = '1';
    console.log('[dashboard] fetching', el.id, el.dataset.url);
    if(window.jQuery){
      if(!jQuery.fn.resizableColumns){ jQuery.fn.resizableColumns = function(){ return this; }; }
      if(!jQuery.fn.perfectScrollbar){ jQuery.fn.perfectScrollbar = function(){ return this; }; }
      if(!jQuery.fn.floatThead){ jQuery.fn.floatThead = function(){ return this; }; }
      if(!jQuery.fn.gridexport){ jQuery.fn.gridexport = function(){ return this; }; }
      if(!jQuery.fn.yiiGridView){ console.warn('[dashboard] stub yiiGridView for',el.id); jQuery.fn.yiiGridView = function(){ return this; }; }
    }
    fetch(el.dataset.url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ console.log('[dashboard] response', el.id, r.status, r.statusText); if(!r.ok) throw new Error('HTTP '+r.status+' '+r.statusText); return r.text(); })
      .then(function(html){
        console.log('[dashboard] fetched', el.id, 'bytes', html.length);
        el.innerHTML = html;
        // кнопка "Увидеть больше" — биндим сразу
        el.querySelectorAll('.btn-toggle-expand').forEach(function(btn){
          if(btn.dataset.bound) return; btn.dataset.bound='1';
          console.log('[dashboard] bind expand btn', el.id);
          btn.addEventListener('click', function(){
            var c = el.querySelector('.expandable-container');
            console.log('[dashboard] toggle expand', el.id, !!c);
            if(c) c.classList.toggle('is-expanded');
            btn.textContent = c && c.classList.contains('is-expanded') ? 'Свернуть' : 'Увидеть больше';
          });
        });
        var scripts = Array.prototype.slice.call(el.querySelectorAll('script'));
        console.log('[dashboard] scripts in', el.id, scripts.length);
        function isAlreadyLoaded(src){
          if(document.querySelector('script[src="'+src+'"]')) return true;
          var fname = src.split('/').pop().split('?')[0];
          return !!document.querySelector('script[src*="'+fname+'"]');
        }
        function loadExternal(src){
          return new Promise(function(res){
            if(isAlreadyLoaded(src)){ console.log('[dashboard] skip already loaded', src); res(); return; }
            console.log('[dashboard] loading external', src);
            var ns=document.createElement('script'); ns.src=src; ns.async=false;
            ns.onload=function(){ console.log('[dashboard] loaded', src); res(); };
            ns.onerror=function(){ console.warn('[dashboard] failed', src); res(); };
            document.head.appendChild(ns);
          });
        }
        var chain = Promise.resolve();
        scripts.forEach(function(s){
          if(s.src){
            chain = chain.then(function(){ return loadExternal(s.src); });
          } else if(s.textContent.trim()){
            (function(code){
              chain = chain.then(function(){
                console.log('[dashboard] inline script', el.id, code.substring(0,120).split(String.fromCharCode(10)).join(' ').substring(0,120));
                if(code.indexOf('krajeeYiiConfirm') !== -1 && typeof window.krajeeYiiConfirm === 'undefined') return;
                try{ (new Function(code))(); }catch(e){ console.error('slot script error',el.id,e, e.stack); }
              });
            })(s.textContent);
          }
        });
        chain = chain.then(function(){
          window.dispatchEvent(new Event('resize'));
          console.log('[dashboard] slot ready', el.id);
        });
        return chain;
      })
      .catch(function(err){
        console.error('dashboard slot failed', el.id, err);
        el.classList.add('has-error');
        el.innerHTML = '<div class="alert alert-warning mb-0">Не удалось загрузить блок <b>'+el.id+'</b>: '+err.message+' <button class="btn btn-sm btn-outline-primary ms-2 btn-retry">Повторить</button> <small class="text-muted">'+(el.dataset.url||'')+'</small></div>';
        var b = el.querySelector('.btn-retry');
        if(b) b.addEventListener('click', function(e){ e.preventDefault(); el.innerHTML='<div class="dashboard-loader text-center p-4"><span class="spinner-border spinner-border-sm"></span> Повтор...</div>'; el.classList.remove('has-error'); loadSlot(el); });
      })
      .finally(function(){ delete el.dataset.loading; });
  }
  function initSlots(){
    console.log('[dashboard] initSlots, found', document.querySelectorAll('.dashboard-slot').length);
    document.querySelectorAll('.dashboard-slot').forEach(function(el){
      if(el.classList.contains('d-none') || el.dataset.loading) return;
      if(!el.querySelector('.dashboard-loader') && el.innerHTML.trim() !== '') return;
      loadSlot(el);
    });
  }
  // Yii registerJs по умолчанию POS_READY (jQuery ready) — DOMContentLoaded уже прошёл, поэтому запускаем сразу + fallback
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initSlots);
  } else {
    initSlots();
  }
  // fallback: если jQuery отложил выполнение — ещё один тик
  setTimeout(initSlots, 300);
})();
JS;
$this->registerJs($js, \yii\web\View::POS_END);
?>
