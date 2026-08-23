<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use app\models\StockMovement;
use app\models\StockSnapshot;
use app\models\StockImportForm;
use app\models\search\StockMovementSearch;
use app\components\StockBalanceService;
use app\models\WbCard;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StockMovementController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'reconcile' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Инлайн-сверка со страницы "Остатки на начало периода": сравнивает фактическое
     * наличие (инвентаризация) с зафиксированным остатком на начало ВЫБРАННОГО периода
     * (а не с расчётным балансом "на сегодня" - в жизни сверяют именно с тем, что
     * было на конкретную дату снапшота). Ожидаемое значение берём из БД по (nm_id,
     * period_date), а не доверяем тому, что прислал браузер - подстраховка от
     * рассинхронизации, если страницу держали открытой долго.
     * Создаёт движение type=adjustment на разницу, датированное этим же периодом.
     * Если расхождений нет - движение не создаётся.
     */
    public function actionReconcile()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $nmId = (int) Yii::$app->request->post('nm_id');
        $actualQtyRaw = Yii::$app->request->post('actual_qty');
        $periodDate = Yii::$app->request->post('period_date');

        if ($nmId <= 0 || empty($periodDate) || $actualQtyRaw === null || $actualQtyRaw === '' || !is_numeric($actualQtyRaw)) {
            return ['success' => false, 'error' => 'Укажите корректные данные для сверки (nmID, период и фактическое количество).'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
            return ['success' => false, 'error' => 'Некорректный формат даты периода.'];
        }

        $actualQty = (int) $actualQtyRaw;

        $snapshot = StockSnapshot::find()->where(['nm_id' => $nmId, 'period_date' => $periodDate])->one();
        if (!$snapshot) {
            return ['success' => false, 'error' => "Снапшот для товара {$nmId} на {$periodDate} не найден."];
        }

        $expectedQty = (int) $snapshot->qty_start;
        $diff = $actualQty - $expectedQty;

        if ($diff === 0) {
            return [
                'success' => true,
                'diff' => 0,
                'message' => 'Расхождений нет, движение не создавалось.',
            ];
        }

        $movement = new StockMovement([
            'nm_id' => $nmId,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'qty' => $diff,
            'movement_date' => $periodDate,
            'comment' => "Сверка на {$periodDate}: остаток на начало периода {$expectedQty}, факт {$actualQty}",
            'source' => StockMovement::SOURCE_MANUAL,
        ]);

        if (!$movement->save()) {
            return ['success' => false, 'error' => implode('; ', $movement->getFirstErrors())];
        }

        return [
            'success' => true,
            'diff' => $diff,
            'message' => $diff > 0 ? "Излишек +{$diff} зафиксирован." : "Недостача {$diff} зафиксирована.",
        ];
    }

    /**
     * AJAX-поиск карточек для select2 в форме добавления/редактирования движения.
     * Ищет по vendorCode, title и nmID (в т.ч. частичное совпадение по nmID).
     */
    public function actionSearchCards($q = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $query = WbCard::find()->andWhere(['is_active' => 1]);

        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($query);
        }

        if ($q !== null && trim($q) !== '') {
            $q = trim($q);
            $query->andWhere(['or',
                ['like', 'vendorCode', $q],
                ['like', 'title', $q],
                ['like', new \yii\db\Expression('CAST(`nmID` AS CHAR)'), $q],
            ]);
        }

        $cards = $query->orderBy(['title' => SORT_ASC])->limit(30)->all();

        return array_map(function (WbCard $c) {
            return [
                'id' => $c->nmID,
                'text' => $c->vendorCode . ' / nmID ' . $c->nmID . ' — ' . $c->title,
            ];
        }, $cards);
    }

    public function actionIndex()
    {
        $searchModel = new StockMovementSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new StockMovement();
        $model->movement_date = date('Y-m-d');
        $model->source = StockMovement::SOURCE_MANUAL;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Движение добавлено.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Движение обновлено.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Запись удалена.');
        return $this->redirect(['index']);
    }

    /**
     * Импорт из Excel: nmId | vendorCode | Количество.
     * Приход - создаёт движение type=production_in с qty = количество из файла.
     * Сверка - сравнивает количество из файла (факт) с текущим расчётным балансом
     *          и создаёт движение type=adjustment с qty = факт - расчётный баланс.
     */
    public function actionImport()
    {
        $form = new StockImportForm();
        $form->movementDate = date('Y-m-d');

        $report = null;

        if (Yii::$app->request->isPost) {
            $form->file = UploadedFile::getInstance($form, 'file');
            $form->load(Yii::$app->request->post());

            if ($form->file && $form->validate()) {
                $report = $this->processImportFile($form);
            }
        }

        return $this->render('import', [
            'form' => $form,
            'report' => $report,
        ]);
    }

    private function processImportFile(StockImportForm $form): array
    {
        $tmpPath = Yii::getAlias('@runtime') . '/stock_import_' . uniqid() . '.' . $form->file->extension;
        $form->file->saveAs($tmpPath);

        $processed = 0;
        $skipped = [];

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            if (empty($rows)) {
                return ['processed' => 0, 'skipped' => ['Файл пустой.']];
            }

            // определяем колонки по заголовку первой строки
            $header = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
            $colNmId = $this->findColumn($header, ['nmid', 'nm_id', 'nm id']);
            $colVendor = $this->findColumn($header, ['vendorcode', 'vendor_code', 'артикул', 'vendor code']);
            $colQty = $this->findColumn($header, ['количество', 'qty', 'quantity', 'кол-во']);

            if ($colQty === null || ($colNmId === null && $colVendor === null)) {
                return [
                    'processed' => 0,
                    'skipped' => ['Не найдены нужные колонки. Ожидаются заголовки: nmId, vendorCode, Количество.'],
                ];
            }

            $companyId = (Yii::$app->has('companyManager') && !Yii::$app->companyManager->isGlobalMode())
                ? Yii::$app->companyManager->getCurrentId()
                : null;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nmId = $colNmId !== null ? trim((string)($row[$colNmId] ?? '')) : null;
                $vendorCode = $colVendor !== null ? trim((string)($row[$colVendor] ?? '')) : null;
                $qtyRaw = $row[$colQty] ?? null;

                if ($qtyRaw === null || $qtyRaw === '') {
                    continue; // пустая строка
                }

                $card = StockMovement::findCardByImportRow($nmId, $vendorCode, $companyId);
                if (!$card) {
                    $skipped[] = "Строка " . ($i + 1) . ": карточка не найдена (nmId={$nmId}, vendorCode={$vendorCode})";
                    continue;
                }

                $qty = (int) round((float) $qtyRaw);

                if ($form->type === StockMovement::TYPE_PRODUCTION_IN) {
                    $this->saveMovement($card->nmID, StockMovement::TYPE_PRODUCTION_IN, $qty, $form->movementDate,
                        'Импорт прихода из Excel');
                } else {
                    $currentBalance = StockBalanceService::getCurrentBalance($card->nmID, $form->movementDate);
                    $diff = $qty - $currentBalance;
                    if ($diff !== 0) {
                        $this->saveMovement($card->nmID, StockMovement::TYPE_ADJUSTMENT, $diff, $form->movementDate,
                            "Сверка: расчётный {$currentBalance}, факт {$qty}");
                    }
                }

                $processed++;
            }
        } finally {
            @unlink($tmpPath);
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    private function saveMovement(int $nmId, string $type, int $qty, string $date, string $comment): void
    {
        $movement = new StockMovement([
            'nm_id' => $nmId,
            'type' => $type,
            'qty' => $qty,
            'movement_date' => $date,
            'comment' => $comment,
            'source' => StockMovement::SOURCE_EXCEL_IMPORT,
        ]);
        $movement->save();
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

    protected function findModel($id): StockMovement
    {
        if (($model = StockMovement::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Запись не найдена.');
    }
}
