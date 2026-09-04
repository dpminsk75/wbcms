<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var app\models\Company $model */
?>
<?php $form = ActiveForm::begin() ?>
<div class="row">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light fw-semibold">Основное</div>
            <div class="card-body">
                <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
                <?= $form->field($model, 'abbreviation')->textInput(['maxlength' => true, 'placeholder' => 'Короткое имя для гридов']) ?>
                <?= $form->field($model, 'inn')->textInput(['maxlength' => true]) ?>
                <?= $form->field($model, 'api_key')->textarea(['rows' => 3, 'placeholder' => 'JWT WB, сохранится как есть']) ?>
                <hr class="my-3">
                <?= $form->field($model, 'is_active')->checkbox() ?>
                <?= $form->field($model, 'fbs_deduct_enabled')->checkbox() ?>
                <?= $form->field($model, 'fbs_deduct_test')->checkbox()->hint('1 — сухое списание в лог, 0 — реальное') ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 border-primary border-opacity-25">
            <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="fas fa-robot me-1"></i> SEO</span>
                <span class="badge bg-light text-muted border">пусто = из params.php</span>
            </div>
            <div class="card-body">
                <?= $form->field($model, 'seo_openrouter_key', [
                    'template' => "{label}\n<div class=\"input-group\">{input}<button class=\"btn btn-outline-secondary\" type=\"button\" id=\"toggle-seo-key\" tabindex=\"-1\"><i class=\"fas fa-eye\"></i></button></div>\n{hint}\n{error}"
                ])->passwordInput(['placeholder'=>'sk-or-...','autocomplete'=>'off','id'=>'company-seo_openrouter_key'])->hint('Оставь пустым — возьмётся из params.php') ?>
                <div class="row">
                    <div class="col-6"><?= $form->field($model, 'seo_openrouter_referer')->textInput(['placeholder'=>'https://wbcms.local']) ?></div>
                    <div class="col-6"><?= $form->field($model, 'seo_openrouter_title')->textInput(['placeholder'=>'wbcms SEO']) ?></div>
                </div>
                <?= $form->field($model, 'seo_model')->textInput(['placeholder'=>'minimax/minimax-m3:free'])->hint('Команда: php yii seo/models — список :free') ?>
                <div class="row">
                    <div class="col-4"><?= $form->field($model, 'seo_daily_limit')->textInput(['type'=>'number','placeholder'=>'20']) ?></div>
                    <div class="col-4"><?= $form->field($model, 'seo_desc_min')->textInput(['type'=>'number','placeholder'=>'2000']) ?></div>
                    <div class="col-4"><?= $form->field($model, 'seo_desc_max')->textInput(['type'=>'number','placeholder'=>'5000']) ?></div>
                </div>
                <?= $form->field($model, 'seo_anti_spam_days')->textInput(['type'=>'number','placeholder'=>'14']) ?>
                <?= $form->field($model, 'seo_prompt')->textarea(['rows'=>6, 'placeholder'=>'Пусто = дефолт: Ты — SEO-специалист Wildberries...']) 
                    ->hint('Переменная {DESC_MIN}/{DESC_MAX} подставится автоматически. Оставь пустым для стандартного.') ?>
                <div class="form-text small text-muted">Длина описания и анти-спам влияют на промпт ИИ.</div>
            </div>
        </div>
    </div>
</div>
<div class="form-group mt-3">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success px-4']) ?>
    <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>
<?php
$js = <<<JS
document.getElementById('toggle-seo-key').addEventListener('click', function(){
  var inp = document.getElementById('company-seo_openrouter_key');
  var icon = this.querySelector('i');
  if(inp.type === 'password'){ inp.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
  else { inp.type = 'password'; icon.classList.add('fa-eye'); icon.classList.remove('fa-eye-slash'); }
});
JS;
$this->registerJs($js);
?>
<?php ActiveForm::end() ?>
