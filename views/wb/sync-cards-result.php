<?php

/** @var yii\web\View $this */
/** @var int $totalFetched */
/** @var array $errors */
/** @var string|null $token */
/** @var string|null $requestUrl */

$this->title = 'Результат синхронизации карточек WB';
?>
<div class="wb-sync-result">
    <h1><?= htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>

    <p><strong>Всего обработано карточек:</strong> <?= (int)$totalFetched ?></p>

    <p><strong>URL запроса:</strong> <?= htmlspecialchars((string)$requestUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <p><strong>Токен (как отправляется в Authorization):</strong></p>
    <pre><?= htmlspecialchars((string)$token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>

    <?php if (!empty($errors)): ?>
        <h3>Ошибки:</h3>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><strong>Ошибок нет.</strong></p>
    <?php endif; ?>
</div>

