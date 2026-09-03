<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\web\UploadedFile;
use yii\helpers\Json;
use PhpOffice\PhpSpreadsheet\IOFactory;
use app\models\WbFbsWarehouse;
use app\models\WbCentralStock;
use app\models\WbVirtualStock;
use app\models\WbCard;
use app\models\WbCardSize;

/**
 * Управление виртуальными складами и остатками FBS.
 */
class WbFbsVirtualController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['manageFbsStocks']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'toggle-virtual' => ['post'],
                    'toggle-consider' => ['post'],
                    'parse-central' => ['post'],
                    'parse-virtual' => ['post'],
                    'save-central' => ['post'],
                    'save-virtual' => ['post'],
                    'upload-one' => ['post'],
                    'upload-all' => ['post'],
                    'delete-virtual' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $isGlobal = Yii::$app->companyManager->isGlobalMode();
        $companyFilter = (!$isGlobal && $companyId) ? $companyId : null;

        // Виртуальные склады для шапки
        $virtualWarehouses = WbFbsWarehouse::find()->where(['is_virtual' => 1]);
        if ($companyFilter) {
            $virtualWarehouses->andWhere(['company_id' => $companyFilter]);
        }
        $virtualWarehouses = $virtualWarehouses->all();

        // Единая таблица: все sku из wbcards_sizes + left join центр/виртуал
        $baseQuery = (new Query())
            ->select([
                'ws.sku', 'ws.nmID', 'ws.chrtID',
                'wc.vendorCode', 'wc.title', 'wc.brand',
                'cs.quantity as central_qty',
                'vs.quantity as virtual_qty',
            ])
            ->from(['ws' => 'wbcards_sizes'])
            ->innerJoin(['wc' => 'wbcards'], 'wc.nmID = ws.nmID');
        if ($companyFilter) {
            $baseQuery->leftJoin(['cs' => 'wb_central_stock'], 'cs.sku = ws.sku AND cs.company_id = :cid', [':cid' => $companyFilter]);
            $baseQuery->leftJoin(['vs' => 'wb_virtual_stock'], 'vs.sku = ws.sku AND vs.company_id = :cid2', [':cid2' => $companyFilter]);
            $baseQuery->andWhere(['wc.company_id' => $companyFilter]);
        } else {
            $baseQuery->leftJoin(['cs' => 'wb_central_stock'], 'cs.sku = ws.sku');
            $baseQuery->leftJoin(['vs' => 'wb_virtual_stock'], 'vs.sku = ws.sku');
        }
        $baseQuery->orderBy(['wc.vendorCode' => SORT_ASC, 'ws.sku' => SORT_ASC]);

        // Фильтр поиска по sku/vendorCode/title
        $q = Yii::$app->request->get('q');
        if (!empty($q)) {
            $baseQuery->andWhere(['or',
                ['like', 'ws.sku', $q],
                ['like', 'wc.vendorCode', $q],
                ['like', 'wc.title', $q],
                ['like', 'ws.nmID', $q],
            ]);
        }

        // Фильтр по количеству виртуал. остатка
        $qtyFilter = Yii::$app->request->get('qty', 'all');
        if ($qtyFilter === 'not_found') {
            $baseQuery->andWhere(['vs.quantity' => null]);
        } elseif ($qtyFilter === 'zero') {
            $baseQuery->andWhere(['vs.quantity' => 0]);
        } elseif ($qtyFilter === '1_9') {
            $baseQuery->andWhere(['between', 'vs.quantity', 1, 9]);
        }

        $whFilter = Yii::$app->request->get('wh', 'all');

        $rows = $baseQuery->all();
        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $rows,
            'pagination' => ['pageSize' => 100],
            'sort' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'virtualWarehouses' => $virtualWarehouses,
            'q' => $q,
            'qtyFilter' => $qtyFilter,
            'whFilter' => $whFilter,
        ]);
    }

    public function actionWarehouseList()
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $isGlobal = Yii::$app->companyManager->isGlobalMode();
        $query = WbFbsWarehouse::find()->where(['is_deleting' => 0])->orderBy(['name' => SORT_ASC]);
        if (!$isGlobal && $companyId) {
            $query->andWhere(['company_id' => $companyId]);
        }
        $warehouses = $query->all();
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('_warehouses', ['warehouses' => $warehouses]);
        }
        return $this->render('_warehouses', ['warehouses' => $warehouses]);
    }

    public function actionToggleVirtual($id)
    {
        $model = WbFbsWarehouse::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Склад не найден');
        }
        $model->is_virtual = $model->is_virtual ? 0 : 1;
        $model->save(false);
        // Для модалки всегда отдаём JSON (fetch с X-Requested-With иногда не проходит isAjax из-за прокси), иначе был 302
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success' => true, 'is_virtual' => (int)$model->is_virtual, 'id' => $model->id];
        }
        Yii::$app->session->setFlash('success', 'Склад ' . $model->name . ' ' . ($model->is_virtual ? 'помечен виртуальным' : 'снят с виртуальных'));
        return $this->redirect(['index']);
    }

    public function actionToggleConsider($id)
    {
        $model = WbFbsWarehouse::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Склад не найден');
        }
        $model->consider_orders = $model->consider_orders ? 0 : 1;
        $model->save(false);
        if (Yii::$app->request->isAjax || Yii::$app->request->isPost) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success' => true, 'consider_orders' => (int)$model->consider_orders, 'is_virtual' => (int)$model->is_virtual, 'id' => $model->id];
        }
        Yii::$app->session->setFlash('success', 'Склад ' . $model->name . ' ' . ($model->consider_orders ? 'учитывает заказы' : 'не учитывает заказы'));
        return $this->redirect(['index']);
    }

    public function actionDeductLog($date = null)
    {
        $dir = Yii::$app->params['wbFbsDeductLogDir'] ?? Yii::getAlias('@runtime/logs');
        if (!is_dir($dir)) $dir = Yii::getAlias('@runtime/logs');
        $files = glob($dir . '/wb-fbs-deduct-*.log');
        rsort($files);
        $selected = $date ? $dir . '/wb-fbs-deduct-' . preg_replace('/[^0-9\-]/','',$date) . '.log' : ($files[0] ?? null);
        $content = '';
        $tail = 2000;
        if ($selected && is_file($selected)) {
            $lines = file($selected, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                $lines = array_slice($lines, -$tail);
                $content = implode("\n", $lines);
            }
        }
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success'=>true,'file'=> $selected ? basename($selected):null,'content'=>$content,'files'=>array_map('basename',$files)];
        }
        return $this->render('deduct-log', ['files'=>$files,'selected'=>$selected,'content'=>$content]);
    }

    /**
     * Парсит Excel для центр. склада и возвращает matched/skipped как в StockSnapshotController.
     * Колонки: Баркод/sku, Артикул продавца/vendorCode, nmID/Артикул, Количество/qty
     */
    public function actionParseCentral()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $this->parseExcelForStock('central');
    }

    public function actionParseVirtual()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $this->parseExcelForStock('virtual');
    }

    private function parseExcelForStock($target)
    {
        $file = UploadedFile::getInstanceByName('file');
        if (!$file) {
            return ['success' => false, 'error' => 'Файл не передан'];
        }
        $tmpPath = Yii::getAlias('@runtime') . '/fbs_import_' . $target . '_' . uniqid() . '.' . $file->extension;
        $file->saveAs($tmpPath);

        $matched = [];
        $skipped = [];
        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            if (empty($rows)) {
                return ['success' => false, 'error' => 'Файл пустой'];
            }
            $header = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
            $colSku = $this->findColumn($header, ['баркод', 'barcode', 'sku', 'штрихкод']);
            $colVendor = $this->findColumn($header, ['артикул продавца', 'vendorcode', 'vendor_code', 'артикул']);
            $colNmId = $this->findColumn($header, ['nmid', 'nm_id', 'nm id', 'артикул wb', 'nm']);
            $colQty = $this->findColumn($header, ['количество', 'qty', 'quantity', 'кол-во', 'остаток', 'остатки']);

            if ($colQty === null || ($colSku === null && $colVendor === null && $colNmId === null)) {
                return ['success' => false, 'error' => 'Не найдены колонки. Нужны: Баркод и/или Артикул продавца и/или nmID + Количество'];
            }

            $companyId = Yii::$app->companyManager->getCurrentId();
            if (Yii::$app->companyManager->isGlobalMode()) {
                // в глобальном режиме берем первую активную компанию или требуем выбора
                $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
            }

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sku = $colSku !== null ? trim((string)($row[$colSku] ?? '')) : null;
                $vendorCode = $colVendor !== null ? trim((string)($row[$colVendor] ?? '')) : null;
                $nmIdRaw = $colNmId !== null ? trim((string)($row[$colNmId] ?? '')) : null;
                $qtyRaw = $row[$colQty] ?? null;
                if ($qtyRaw === null || $qtyRaw === '' || !is_numeric($qtyRaw)) {
                    continue;
                }
                $resolved = $this->resolveSku($sku, $vendorCode, $nmIdRaw, $companyId);
                if (!$resolved) {
                    $skipped[] = "Строка " . ($i + 1) . ": товар не найден (sku={$sku}, vendor={$vendorCode}, nmID={$nmIdRaw})";
                    continue;
                }
                $matched[] = ['sku' => $resolved['sku'], 'nmID' => $resolved['nmID'], 'chrtID' => $resolved['chrtID'], 'qty' => (int)round((float)$qtyRaw)];
            }
        } finally {
            @unlink($tmpPath);
        }
        return ['success' => true, 'matched' => $matched, 'skipped' => $skipped];
    }

    private function resolveSku($sku, $vendorCode, $nmIdRaw, $companyId)
    {
        // 1) по sku напрямую в wbcards_sizes
        if (!empty($sku)) {
            $row = (new Query())->select(['sku','nmID','chrtID'])->from('wbcards_sizes')->where(['sku' => $sku])->one();
            if ($row) {
                return $row;
            }
        }
        // 2) по nmID
        if (!empty($nmIdRaw) && ctype_digit($nmIdRaw)) {
            $card = WbCard::find()->where(['nmID' => (int)$nmIdRaw])->one();
            if ($card) {
                $size = WbCardSize::find()->where(['nmID' => (int)$nmIdRaw])->one();
                if ($size) {
                    return ['sku' => $size->sku, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID];
                }
                // если у карточки 1 размер - берем его, иначе пропускаем (неоднозначно)
                return null;
            }
        }
        // 3) по vendorCode
        if (!empty($vendorCode)) {
            $q = WbCard::find()->where(['vendorCode' => trim($vendorCode)]);
            if ($companyId) {
                $q->andWhere(['company_id' => $companyId]);
            }
            $card = $q->one();
            if ($card) {
                $size = WbCardSize::find()->where(['nmID' => $card->nmID])->one();
                if ($size) {
                    // если несколько размеров - берем первый, но лучше требовать sku
                    // для однозначности: если у товара >1 sku - пропускаем
                    $cnt = WbCardSize::find()->where(['nmID' => $card->nmID])->count();
                    if ($cnt == 1) {
                        return ['sku' => $size->sku, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID];
                    }
                }
            }
        }
        return null;
    }

    public function actionSaveCentral()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $this->saveStockBatch('central');
    }

    public function actionSaveVirtual()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $this->saveStockBatch('virtual');
    }

    private function saveStockBatch($target)
    {
        $changesJson = Yii::$app->request->post('changes');
        $changes = json_decode((string)$changesJson, true);
        if (!is_array($changes)) {
            return ['success' => false, 'error' => 'Некорректные данные'];
        }
        $companyId = Yii::$app->companyManager->getCurrentId();
        if (Yii::$app->companyManager->isGlobalMode()) {
            $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        $modelClass = $target === 'central' ? WbCentralStock::class : WbVirtualStock::class;
        $table = $target === 'central' ? 'wb_central_stock' : 'wb_virtual_stock';

        $processed = 0;
        $errors = [];
        foreach ($changes as $c) {
            $sku = $c['sku'] ?? null;
            $qty = $c['qty'] ?? null;
            if (empty($sku) || $qty === null || !is_numeric($qty)) {
                $errors[] = "Пропущено sku={$sku}";
                continue;
            }
            $size = WbCardSize::findOne(['sku' => $sku]);
            if (!$size) {
                $errors[] = "SKU $sku не найден в wbcards_sizes";
                continue;
            }
            $row = [
                'company_id' => $companyId,
                'sku' => $sku,
                'nmID' => $size->nmID,
                'chrtID' => $size->chrtID,
                'quantity' => (int)round((float)$qty),
            ];
            try {
                Yii::$app->db->createCommand()->upsert($table, $row, [
                    'nmID' => $row['nmID'],
                    'chrtID' => $row['chrtID'],
                    'quantity' => $row['quantity'],
                ])->execute();
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = $sku . ': ' . $e->getMessage();
            }
        }
        return ['success' => true, 'processed' => $processed, 'errors' => $errors];
    }

    public function actionDeleteVirtual()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $sku = Yii::$app->request->post('sku');
        if (empty($sku)) {
            return ['success' => false, 'error' => 'sku не передан'];
        }
        $companyId = Yii::$app->companyManager->getCurrentId();
        if (Yii::$app->companyManager->isGlobalMode()) {
            $companyId = $companyId ?: (new \yii\db\Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        Yii::$app->db->createCommand()->delete('wb_virtual_stock', ['company_id' => $companyId, 'sku' => $sku])->execute();
        return ['success' => true];
    }

    /**
     * Выгрузка одной строки виртуал. остатка на все is_virtual склады: PUT /api/v3/stocks/{warehouseId}
     * Чекбокс теста: если test=1 - эмуляция без реального PUT, вывод в консоль браузера и лог
     */
    public function actionUploadOne()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $sku = Yii::$app->request->post('sku');
        $amountRaw = Yii::$app->request->post('amount');
        $isTest = (bool)Yii::$app->request->post('test');
        if (empty($sku)) {
            return ['success' => false, 'error' => 'sku не передан'];
        }
        $companyId = Yii::$app->companyManager->getCurrentId();
        if (Yii::$app->companyManager->isGlobalMode()) {
            $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        $stock = WbVirtualStock::find()->where(['company_id' => $companyId, 'sku' => $sku])->one();
        // Разрешаем выгрузку несохранённого черновика: берём amount из запроса, иначе из БД
        if (!$stock) {
            if ($amountRaw === null || $amountRaw === '' || !is_numeric($amountRaw)) {
                return ['success' => false, 'error' => 'Виртуальный остаток не найден — сначала Сохранить или введите количество и попробуйте снова'];
            }
            $size = WbCardSize::findOne(['sku' => $sku]);
            if (!$size) {
                return ['success' => false, 'error' => "SKU $sku не найден в wbcards_sizes"];
            }
            $stock = new WbVirtualStock();
            $stock->company_id = $companyId;
            $stock->sku = $sku;
            $stock->nmID = $size->nmID;
            $stock->chrtID = $size->chrtID;
            $stock->quantity = (int)$amountRaw;
            // автосохраним черновик чтобы следующий раз не падать
            Yii::$app->db->createCommand()->upsert('wb_virtual_stock', [
                'company_id' => $companyId, 'sku' => $sku, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID, 'quantity' => (int)$amountRaw,
            ], ['quantity' => (int)$amountRaw, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID])->execute();
        } elseif ($amountRaw !== null && $amountRaw !== '' && is_numeric($amountRaw)) {
            // если в инпуте другое значение чем в БД — выгружаем именно его (несохранённый черновик)
            $stock->quantity = (int)$amountRaw;
        }
        $warehouses = WbFbsWarehouse::find()->where(['company_id' => $companyId, 'is_virtual' => 1])->all();
        $whFilter = Yii::$app->request->post('warehouseId') ?? Yii::$app->request->post('wh');
        if (!empty($whFilter) && $whFilter !== 'all') {
            $warehouses = array_values(array_filter($warehouses, fn($w) => (string)$w->warehouseId === (string)$whFilter));
            if (empty($warehouses)) {
                return ['success' => false, 'error' => 'Склад ' . $whFilter . ' не найден среди виртуальных'];
            }
        }
        if (empty($warehouses)) {
            return ['success' => false, 'error' => 'Нет виртуальных складов (отметьте is_virtual)'];
        }
        $company = (new Query())->from('companies')->where(['id' => $companyId])->one();
        $token = $company['api_key'] ?? null;
        if (!$token && !$isTest) {
            return ['success' => false, 'error' => 'Нет токена компании'];
        }

        $results = [];
        foreach ($warehouses as $wh) {
            $payload = ['stocks' => [['chrtId' => (int)$stock->chrtID, 'amount' => (int)$stock->quantity]]];
            $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$wh->warehouseId}";
            if ($isTest) {
                Yii::info("[DRY] PUT $url payload=" . json_encode($payload, JSON_UNESCAPED_UNICODE), 'wb_fbs');
                $results[] = ['warehouseId' => $wh->warehouseId, 'ok' => true, 'dry' => true, 'payload' => $payload, 'url' => $url];
                continue;
            }
            try {
                $resp = Yii::$app->wbHttpClient->request('PUT', $url, $payload, $token, $companyId, null, true);
                $ok = $resp->isOk;
                $results[] = ['warehouseId' => $wh->warehouseId, 'ok' => $ok, 'status' => $resp->statusCode, 'body' => substr($resp->content, 0, 500), 'payload' => $payload];
                usleep(300000);
            } catch (\Throwable $e) {
                $results[] = ['warehouseId' => $wh->warehouseId, 'ok' => false, 'error' => $e->getMessage(), 'payload' => $payload];
            }
        }
        if (!$isTest) {
            foreach ($warehouses as $wh) {
                Yii::$app->db->createCommand()->upsert('wb_fbs_stock', [
                    'company_id' => $companyId,
                    'warehouseId' => $wh->warehouseId,
                    'sku' => $stock->sku,
                    'amount' => (int)$stock->quantity,
                    'nmID' => $stock->nmID,
                    'chrtID' => $stock->chrtID,
                ], ['amount' => (int)$stock->quantity, 'nmID' => $stock->nmID, 'chrtID' => $stock->chrtID])->execute();
            }
        }

        return ['success' => true, 'dry' => $isTest, 'results' => $results];
    }

    /**
     * Выгрузка всех виртуал. остатков на все виртуал. склады
     * Чекбокс теста: test=1 - эмуляция
     */
    public function actionUploadAll()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $isTest = (bool)Yii::$app->request->post('test');
        $companyId = Yii::$app->companyManager->getCurrentId();
        if (Yii::$app->companyManager->isGlobalMode()) {
            $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        $stocks = WbVirtualStock::find()->where(['company_id' => $companyId])->all();
        if (empty($stocks)) {
            return ['success' => false, 'error' => 'Нет виртуальных остатков для выгрузки'];
        }
        $warehouses = WbFbsWarehouse::find()->where(['company_id' => $companyId, 'is_virtual' => 1])->all();
        $whFilter = Yii::$app->request->post('warehouseId') ?? Yii::$app->request->post('wh');
        if (!empty($whFilter) && $whFilter !== 'all') {
            $warehouses = array_values(array_filter($warehouses, fn($w) => (string)$w->warehouseId === (string)$whFilter));
            if (empty($warehouses)) {
                return ['success' => false, 'error' => 'Склад ' . $whFilter . ' не найден среди виртуальных'];
            }
        }
        if (empty($warehouses)) {
            return ['success' => false, 'error' => 'Нет виртуальных складов'];
        }
        $company = (new Query())->from('companies')->where(['id' => $companyId])->one();
        $token = $company['api_key'] ?? null;
        if (!$token && !$isTest) {
            return ['success' => false, 'error' => 'Нет токена'];
        }

        $payloadStocks = [];
        foreach ($stocks as $s) {
            $payloadStocks[] = ['chrtId' => (int)$s->chrtID, 'amount' => (int)$s->quantity];
        }

        $results = [];
        foreach ($warehouses as $wh) {
            $chunks = array_chunk($payloadStocks, 1000);
            foreach ($chunks as $idx => $chunk) {
                $payload = ['stocks' => $chunk];
                $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$wh->warehouseId}";
                if ($isTest) {
                    Yii::info("[DRY] PUT $url payload=" . json_encode($payload, JSON_UNESCAPED_UNICODE), 'wb_fbs');
                    $results[] = ['warehouseId' => $wh->warehouseId, 'chunk' => $idx + 1, 'ok' => true, 'dry' => true, 'payload' => $payload, 'url' => $url];
                    continue;
                }
                try {
                    $resp = Yii::$app->wbHttpClient->request('PUT', $url, $payload, $token, $companyId, null, true);
                    if (!$resp->isOk) {
                        $results[] = ['warehouseId' => $wh->warehouseId, 'chunk' => $idx + 1, 'ok' => false, 'status' => $resp->statusCode, 'body' => substr($resp->content, 0, 500), 'payload' => $payload];
                    } else {
                        $results[] = ['warehouseId' => $wh->warehouseId, 'chunk' => $idx + 1, 'ok' => true, 'payload' => $payload];
                    }
                    usleep(300000);
                } catch (\Throwable $e) {
                    $results[] = ['warehouseId' => $wh->warehouseId, 'chunk' => $idx + 1, 'ok' => false, 'error' => $e->getMessage(), 'payload' => $payload];
                }
            }
            if (!$isTest) {
                foreach ($payloadStocks as $ps) {
                    $size = WbCardSize::findOne(['sku' => $ps['sku']]);
                    Yii::$app->db->createCommand()->upsert('wb_fbs_stock', [
                        'company_id' => $companyId,
                        'warehouseId' => $wh->warehouseId,
                        'sku' => $ps['sku'],
                        'amount' => $ps['amount'],
                        'nmID' => $size->nmID ?? null,
                        'chrtID' => $size->chrtID ?? null,
                    ], ['amount' => $ps['amount']])->execute();
                }
            }
        }

        return ['success' => true, 'dry' => $isTest, 'results' => $results];
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
}
