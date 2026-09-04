<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var int $nmID */
/** @var \app\models\WbSeoTarget[] $targets */
$targets = $targets ?? \app\models\WbSeoTarget::find()->where(['nmID'=>$nmID])->orderBy(['priority'=>SORT_ASC])->all();
?>
<div class="card border-warning" id="seo-targets-block-<?= $nmID ?>">
    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-bullseye text-warning"></i> Целевые запросы (приоритет в промпте)</span>
        <span class="small text-muted"><?= count($targets) ?>/10</span>
    </div>
    <div class="card-body">
        <div class="mb-2" id="seo-targets-list-<?= $nmID ?>">
            <?php foreach ($targets as $t): ?>
                <span class="badge bg-warning text-dark me-1 mb-1" style="font-size:12px">
                    <?= Html::encode($t->phrase) ?>
                    <a href="#" class="text-dark ms-1 seo-target-remove" data-id="<?= $t->id ?>" data-nmid="<?= $nmID ?>" title="Удалить">×</a>
                </span>
            <?php endforeach; ?>
            <?php if (empty($targets)): ?><span class="text-muted small">Пока нет — добавь ниже</span><?php endif; ?>
        </div>
        <div class="input-group input-group-sm" style="max-width:520px">
            <input type="text" id="seo-target-input-<?= $nmID ?>" class="form-control seo-target-input" data-nmid="<?= $nmID ?>" placeholder="напр: дневник школьника 12 лет">
            <button class="btn btn-warning seo-target-add" data-nmid="<?= $nmID ?>"><i class="fas fa-plus"></i> Добавить</button>
        </div>
        <div class="small text-muted mt-1">Enter — добавить. До 10, идут в system с приоритетом. Пусто = только статистика.</div>
    </div>
</div>
<?php
$addUrl = Url::to(['/seo/add-target']);
$removeUrl = Url::to(['/seo/remove-target']);
$csrf = Yii::$app->request->csrfToken;
$js = <<<JS
(function(){
  var nmID = $nmID;
  var block = document.getElementById('seo-targets-block-'+nmID);
  var addBtn = block.querySelector('.seo-target-add');
  var input = document.getElementById('seo-target-input-'+nmID);
  var list = document.getElementById('seo-targets-list-'+nmID);
  var counter = block.querySelector('.card-header .small');
  if(!addBtn || !input) return;
  function updateCounter(){
    var cnt = list.querySelectorAll('.badge').length;
    if(counter) counter.textContent = cnt + '/10';
  }
  function bindRemove(a){
    a.addEventListener('click', function(e){
      e.preventDefault();
      fetch('$removeUrl', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body:'_csrf=$csrf&id='+this.dataset.id})
        .then(r=>r.json()).then(d=>{
          if(d.success){
            a.closest('.badge').remove();
            if(!list.querySelector('.badge')) list.innerHTML = '<span class="text-muted small">Пока нет — добавь ниже</span>';
            updateCounter();
          } else alert(d.error);
        });
    });
  }
  block.querySelectorAll('.seo-target-remove').forEach(bindRemove);
  addBtn.addEventListener('click', function(){
    var phrase = input.value.trim();
    if(!phrase) return;
    addBtn.disabled = true;
    fetch('$addUrl', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body:'_csrf=$csrf&nmID='+encodeURIComponent(nmID)+'&phrase='+encodeURIComponent(phrase)})
      .then(r=>r.json()).then(d=>{
        addBtn.disabled = false;
        if(d.success){
          // убрать placeholder
          var ph = list.querySelector('.text-muted.small');
          if(ph && !list.querySelector('.badge')) ph.remove();
          var badge = document.createElement('span');
          badge.className = 'badge bg-warning text-dark me-1 mb-1';
          badge.style.fontSize = '12px';
          badge.innerHTML = phrase.replace(/</g,'&lt;') + ' <a href="#" class="text-dark ms-1 seo-target-remove" data-id="'+d.id+'" title="Удалить">×</a>';
          list.appendChild(badge);
          bindRemove(badge.querySelector('.seo-target-remove'));
          input.value = ''; input.focus();
          updateCounter();
        } else alert(d.error||'Ошибка');
      }).catch(function(){ addBtn.disabled=false; });
  });
  input.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); addBtn.click(); } });
})();
JS;
$this->registerJs($js);
?>
