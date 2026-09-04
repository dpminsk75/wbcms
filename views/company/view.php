<?php
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Company $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Компании', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$isSeoEmpty = !$model->seo_openrouter_key && !$model->seo_model && !$model->seo_daily_limit;
?>
<div class="company-view">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить (деактивировать)', ['delete', 'id' => $model->id], ['class' => 'btn btn-danger', 'data-method' => 'post', 'data-confirm' => 'Деактивировать?']) ?>
    </p>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light fw-semibold">Основное</div>
                <?= DetailView::widget([
                    'model' => $model,
                    'options' => ['class'=>'table table-bordered mb-0'],
                    'attributes' => [
                        'id',
                        'name',
                        'abbreviation',
                        'inn',
                        ['attribute' => 'api_key', 'format'=>'raw', 'value' => $model->api_key ? '<code>'.Html::encode(mb_substr($model->api_key,0,16)).'…</code> <span class="badge bg-success">задан</span>' : '<span class="text-muted">—</span>'],
                        ['attribute'=>'is_active','format'=>'raw','value'=>$model->is_active ? '<span class="badge bg-success">Да</span>' : '<span class="badge bg-secondary">Нет</span>'],
                        ['attribute'=>'fbs_deduct_enabled','format'=>'raw','value'=>$model->fbs_deduct_enabled ? '<span class="badge bg-success">Да</span>' : '<span class="badge bg-secondary">Нет</span>'],
                        ['attribute'=>'fbs_deduct_test','format'=>'raw','value'=>$model->fbs_deduct_test ? '<span class="badge bg-warning text-dark">Тест</span>' : '<span class="badge bg-danger">Боевой</span>'],
                        'created_at:datetime',
                        'updated_at:datetime',
                    ],
                ]) ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-primary border-opacity-25 <?= $isSeoEmpty ? 'opacity-75' : '' ?>">
                <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-robot me-1"></i> SEO</span>
                    <?php if($isSeoEmpty): ?><span class="badge bg-light text-muted border">из params.php</span><?php else: ?><span class="badge bg-primary">переопределено</span><?php endif; ?>
                </div>
                <?= DetailView::widget([
                    'model' => $model,
                    'options' => ['class'=>'table table-bordered mb-0'],
                    'attributes' => [
                        ['attribute'=>'seo_openrouter_key','format'=>'raw','value'=>$model->seo_openrouter_key ? '<code>'.Html::encode(mb_substr($model->seo_openrouter_key,0,12)).'…</code> <span class="badge bg-success">задан</span>' : '<span class="text-muted">из params</span>'],
                        ['attribute'=>'seo_openrouter_referer','value'=>$model->seo_openrouter_referer ?: null,'format'=>['text'],'captionOptions'=>['style'=>'color:#888']],
                        ['attribute'=>'seo_openrouter_title','value'=>$model->seo_openrouter_title ?: null],
                        ['attribute'=>'seo_model','format'=>'raw','value'=>$model->seo_model ? '<code>'.Html::encode($model->seo_model).'</code>' : '<span class="text-muted">из params</span>'],
                        ['attribute'=>'seo_daily_limit','value'=>$model->seo_daily_limit ?: null],
                        ['attribute'=>'seo_desc_min','value'=>$model->seo_desc_min ?: null],
                        ['attribute'=>'seo_desc_max','value'=>$model->seo_desc_max ?: null],
                        ['attribute'=>'seo_anti_spam_days','value'=>$model->seo_anti_spam_days ?: null],
                        ['attribute'=>'seo_prompt','format'=>'raw','value'=>$model->seo_prompt ? '<div style="white-space:pre-wrap;max-height:200px;overflow:auto;background:#f8f9fa;padding:8px;border-radius:4px">'.Html::encode($model->seo_prompt).'</div>' : '<span class="text-muted">дефолт</span>'],
                    ],
                ]) ?>
                <div class="card-footer small text-muted">Пустые поля = берутся из <code>config/params.php</code>. Команда <code>php yii seo/models</code>.</div>
            </div>
        </div>
    </div>
</div>
