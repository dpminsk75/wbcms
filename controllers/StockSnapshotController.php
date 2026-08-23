<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\StockSnapshot;
use app\models\StockMovement;
use app\models\search\StockSnapshotSearch;
use app\components\StockBalanceService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use yii\web\UploadedFile;

class StockSnapshotController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'parse-availability' => ['post'],
                    'reconcile-bulk' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex($period = null)
    {
        $period = $period && preg_match('/^\d{4}-\d{2}$/', $period) ? $period : date('Y-m');

        $searchModel = new StockSnapshotSearch();
        $searchModel->period_date = $period . '-01'; // Явно устанавливаем период
        $queryParams = Yii::$app->request->queryParams;
        // $queryParams['StockSnapshotSearch']['period_date'] = $period . '-01'; // Эту строку можно удалить или закомментировать, так как мы уже установили значение напрямую
        $dataProvider = $searchModel->search($queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'period' => $period,
        ]);
    }

    public function actionCreate()
    {
        $model = new StockSnapshot();
        // по умолчанию предлагаем 1-е число текущего месяца - снапшоты всегда на начало периода
        $model->period_date = date('Y-m-01');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Снапшот сохранён.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Снапшот обновлён.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Снапшот удалён.');
        return $this->redirect(['index']);
    }

    /**
     * Подставить в форму текущий расчётный баланс на дату снапшота (Смоленск)
     * - удобно, когда заводите снапшот вручную и хотите свериться,
     *   а не считать в уме qty_start + приходы - расходы.
     */
    public function actionSuggestBalance($nm_id, $on_date)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['balance' => StockBalanceService::getCurrentBalance((int)$nm_id, $on_date)];
    }

    /**
     * Парсит Excel (nmId/vendorCode + Количество) и просто ВОЗВРАЩАЕТ найденные
     * соответствия JSON'ом - никуда в БД не пишет. Использует JS на странице index,
     * чтобы подставить значения в поля "Наличие" для проверки пользователем перед
     * фактическим сохранением (кнопка "Сохранить все наличия").
     */
    public function actionParseAvailability()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            return ['success' => false, 'error' => 'Файл не передан.'];
        }

        $tmpPath = Yii::getAlias('@runtime') . '/availability_import_' . uniqid() . '.' . $file->extension;
        $file->saveAs($tmpPath);

        $matched = [];
        $skipped = [];

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            if (empty($rows)) {
                return ['success' => false, 'error' => 'Файл пустой.'];
            }

            $header = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
            $colNmId = $this->findColumn($header, ['nmid', 'nm_id', 'nm id', 'id']);
            $colVendor = $this->findColumn($header, ['vendorcode', 'vendor_code', 'артикул']);
            $colQty = $this->findColumn($header, ['количество', 'qty', 'quantity', 'кол-во', 'остаток', 'наличие']);

            if ($colQty === null || ($colNmId === null && $colVendor === null)) {
                return ['success' => false, 'error' => 'Не найдены нужные колонки. Ожидаются заголовки: nmID и/или vendorCode, Количество.'];
            }

            $companyId = null;
            if (Yii::$app->has('companyManager') && !Yii::$app->companyManager->isGlobalMode()) {
                $companyId = Yii::$app->companyManager->getCurrentId();
            }

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nmId = $colNmId !== null ? trim((string)($row[$colNmId] ?? '')) : null;
                $vendorCode = $colVendor !== null ? trim((string)($row[$colVendor] ?? '')) : null;
                $qtyRaw = $row[$colQty] ?? null;

                if ($qtyRaw === null || $qtyRaw === '' || !is_numeric($qtyRaw)) {
                    continue; // пустая/некорректная строка - тихо пропускаем
                }

                $card = StockMovement::findCardByImportRow($nmId, $vendorCode, $companyId);
                if (!$card) {
                    $skipped[] = "Строка " . ($i + 1) . ": товар не найден (nmId={$nmId}, vendorCode={$vendorCode})";
                    continue;
                }

                $matched[] = ['nm_id' => $card->nmID, 'qty' => (int) round((float) $qtyRaw)];
            }
        } finally {
            @unlink($tmpPath);
        }

        return ['success' => true, 'matched' => $matched, 'skipped' => $skipped];
    }

    /**
     * Массовая сверка: то же самое, что и одиночное "Сверить", но для всех строк
     * с заполненным "Наличие" разом. Сравнивает факт с qty_start снапшота именно
     * того периода, что указан в change, и создаёт движение-корректировку на
     * разницу (qty_start НЕ перезаписывается - только движение, как и в одиночной сверке).
     */
    public function actionReconcileBulk()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $changesJson = Yii::$app->request->post('changes');
        $changes = json_decode((string)$changesJson, true);

        if (!is_array($changes)) {
            return ['success' => false, 'error' => 'Некорректные данные для сохранения.'];
        }

        $processed = 0;
        $noDiff = 0;
        $skipped = 0;
        $errors = [];

        foreach ($changes as $change) {
            $nmId = (int) ($change['nm_id'] ?? 0);
            $periodDate = $change['period_date'] ?? null;
            $actualQtyRaw = $change['actual_qty'] ?? null;

            if ($nmId <= 0 || empty($periodDate) || $actualQtyRaw === null || $actualQtyRaw === '' || !is_numeric($actualQtyRaw)) {
                $skipped++;
                $errors[] = "Пропущено: некорректные данные (nmID={$nmId}, период={$periodDate})";
                continue;
            }

            $snapshot = StockSnapshot::find()->where(['nm_id' => $nmId, 'period_date' => $periodDate])->one();
            if (!$snapshot) {
                $skipped++;
                $errors[] = "Снапшот для nmID {$nmId} на {$periodDate} не найден.";
                continue;
            }

            $actualQty = (int) round((float) $actualQtyRaw);
            $expectedQty = (int) $snapshot->qty_start;
            $diff = $actualQty - $expectedQty;

            if ($diff === 0) {
                $noDiff++;
                continue;
            }

            $movement = new StockMovement([
                'nm_id' => $nmId,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'qty' => $diff,
                'movement_date' => $periodDate,
                'comment' => "Массовая сверка на {$periodDate}: остаток на начало периода {$expectedQty}, факт {$actualQty}",
                'source' => StockMovement::SOURCE_MANUAL,
            ]);

            if ($movement->save()) {
                $processed++;
            } else {
                $skipped++;
                $errors[] = "Ошибка сохранения движения для nmID {$nmId}: " . implode('; ', $movement->getFirstErrors());
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'no_diff' => $noDiff,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function findColumn(array $header, array $variants): ?int
    {
        foreach ($header as $idx => $value) {
            if (in_array($value, $variants, true)) {
                return $idx;
            }
        }
        return null;
    }

    protected function findModel($id): StockSnapshot
    {
        if (($model = StockSnapshot::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Снапшот не найден.');
    }
}
