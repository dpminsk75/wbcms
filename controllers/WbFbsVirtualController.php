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
use app\models\OurWarehouse;
use app\models\WbCard;
use app\models\WbCardSize;
use app\models\WbStockBalance;
use app\services\StockService;

/**
 * Управление виртуальными складами и остатками FBS — теперь на wb_stock_balance.
 * Центральный физический (is_central) и оперативный физический is_fbs (Is Fbs) — оба OurWarehouse.
 * Виртуальные склады WB (WbFbsWarehouse is_virtual) — только для выгрузки PUT.
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

        // WB-виртуальные склады для шапки (куда грузим)
        $virtualWarehouses = WbFbsWarehouse::find()->where(['is_virtual' => 1]);
        if ($companyFilter) {
            $virtualWarehouses->andWhere(['company_id' => $companyFilter]);
        }
        $virtualWarehouses = $virtualWarehouses->all();

        // OurWarehouse ids для балансов
        $centralId = null;
        $fbsId = null;
        if ($companyFilter) {
            $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyFilter,'is_central'=>1])->scalar();
            $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyFilter,'is_fbs'=>1])->scalar();
        } else {
            $centralId = OurWarehouse::find()->select('id')->where(['is_central'=>1])->scalar();
            $fbsId = OurWarehouse::find()->select('id')->where(['is_fbs'=>1])->scalar();
        }
        // fallback если нет — пробуем первый склад компании
        if (!$centralId && $companyFilter) $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyFilter])->scalar();
        if (!$fbsId && $companyFilter) $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyFilter,'is_active'=>1])->scalar();

        // Единая таблица: все sku из wbcards_sizes + left join балансы OurWarehouse
        $baseQuery = (new Query())
            ->select([
                'ws.sku', 'ws.nmID', 'ws.chrtID',
                'wc.vendorCode', 'wc.title', 'wc.brand',
                'cb.quantity as central_qty',
                'fb.quantity as virtual_qty',
            ])
            ->from(['ws' => 'wbcards_sizes'])
            ->innerJoin(['wc' => 'wbcards'], 'wc.nmID = ws.nmID');
        if ($companyFilter) {
            $baseQuery->leftJoin(['cb' => 'wb_stock_balance'], 'cb.sku = ws.sku AND cb.company_id = :cid AND cb.warehouseId = :centralId', [':cid' => $companyFilter, ':centralId'=>$centralId]);
            $baseQuery->leftJoin(['fb' => 'wb_stock_balance'], 'fb.sku = ws.sku AND fb.company_id = :cid2 AND fb.warehouseId = :fbsId', [':cid2' => $companyFilter, ':fbsId'=>$fbsId]);
            $baseQuery->andWhere(['wc.company_id' => $companyFilter]);
        } else {
            $baseQuery->leftJoin(['cb' => 'wb_stock_balance'], 'cb.sku = ws.sku AND cb.warehouseId = :centralId2', [':centralId2'=>$centralId]);
            $baseQuery->leftJoin(['fb' => 'wb_stock_balance'], 'fb.sku = ws.sku AND fb.warehouseId = :fbsId2', [':fbsId2'=>$fbsId]);
        }
        $baseQuery->orderBy(['wc.vendorCode' => SORT_ASC, 'ws.sku' => SORT_ASC]);

        $q = Yii::$app->request->get('q');
        if (!empty($q)) {
            $baseQuery->andWhere(['or',
                ['like', 'ws.sku', $q],
                ['like', 'wc.vendorCode', $q],
                ['like', 'wc.title', $q],
                ['like', 'ws.nmID', $q],
            ]);
        }
        $qtyFilter = Yii::$app->request->get('qty', 'all');
        if ($qtyFilter === 'not_found') {
            $baseQuery->andWhere(['fb.quantity' => null]);
        } elseif ($qtyFilter === 'zero') {
            $baseQuery->andWhere(['fb.quantity' => 0]);
        } elseif ($qtyFilter === '1_9') {
            $baseQuery->andWhere(['between', 'fb.quantity', 1, 9]);
        }
        $whFilter = Yii::$app->request->get('wh', 'all');

        $rows = $baseQuery->all();
        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $rows,
            'pagination' => ['pageSize' => 100],
            'sort' => false,
        ]);
        $centralName = $centralId ? OurWarehouse::find()->select('name')->where(['id'=>$centralId])->scalar() : '—';
        $fbsName = $fbsId ? OurWarehouse::find()->select('name')->where(['id'=>$fbsId])->scalar() : '—';

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'virtualWarehouses' => $virtualWarehouses,
            'q' => $q,
            'qtyFilter' => $qtyFilter,
            'whFilter' => $whFilter,
            'centralId'=>$centralId,
            'fbsId'=>$fbsId,
            'centralName'=>$centralName,
            'fbsName'=>$fbsName,
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
        if (!empty($sku)) {
            $row = (new Query())->select(['sku','nmID','chrtID'])->from('wbcards_sizes')->where(['sku' => $sku])->one();
            if ($row) {
                return $row;
            }
        }
        if (!empty($nmIdRaw) && ctype_digit($nmIdRaw)) {
            $card = WbCard::find()->where(['nmID' => (int)$nmIdRaw])->one();
            if ($card) {
                $size = WbCardSize::find()->where(['nmID' => (int)$nmIdRaw])->one();
                if ($size) {
                    return ['sku' => $size->sku, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID];
                }
                return null;
            }
        }
        if (!empty($vendorCode)) {
            $q = WbCard::find()->where(['vendorCode' => trim($vendorCode)]);
            if ($companyId) {
                $q->andWhere(['company_id' => $companyId]);
            }
            $card = $q->one();
            if ($card) {
                $size = WbCardSize::find()->where(['nmID' => $card->nmID])->one();
                if ($size) {
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
        // OurWarehouse ids
        $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_central'=>1])->scalar();
        $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        if (!$centralId) $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId])->scalar();
        if (!$fbsId) $fbsId = $centralId;
        $targetId = $target==='central' ? $centralId : $fbsId;

        if ($target === 'virtual') {
            // тонкий момент: Количество (is_fbs) правится только через TRANSFER Центральный ↔ Оперативный
            $plus = []; $minus = [];
            foreach ($changes as $c) {
                $sku = $c['sku'] ?? null;
                $newQty = $c['qty'] ?? null;
                if (empty($sku) || $newQty===null || !is_numeric($newQty)) continue;
                $newQty = (int)round((float)$newQty);
                $oldQty = WbStockBalance::find()->select('quantity')->where(['company_id'=>$companyId,'warehouseId'=>$fbsId,'sku'=>$sku])->scalar();
                $oldQty = $oldQty===false ? 0 : (int)$oldQty;
                $delta = $newQty - $oldQty;
                if ($delta===0) continue;
                if ($delta>0) $plus[] = ['sku'=>$sku,'qty'=>$delta];
                else $minus[] = ['sku'=>$sku,'qty'=> -$delta];
            }
            $errors=[];
            $processed=0;
            if (!empty($plus)) {
                $res = StockService::transfer($companyId, $centralId, $fbsId, 'fbs_virtual_adjust', null, $plus, Yii::$app->user->id);
                if (!$res['ok']) return ['success'=>false,'error'=>$res['error'],'processed'=>0];
                $processed += count($plus);
            }
            if (!empty($minus)) {
                $res = StockService::transfer($companyId, $fbsId, $centralId, 'fbs_virtual_adjust', null, $minus, Yii::$app->user->id);
                if (!$res['ok']) return ['success'=>false,'error'=>$res['error'],'processed'=>$processed];
                $processed += count($minus);
            }
            // создаем wb_doc TRANSFER для истории (опционально, ledger уже есть)
            // StockService уже создал ledger с doc_type fbs_virtual_adjust
            return ['success'=>true,'processed'=>$processed,'errors'=>$errors];
        }

        // central — прямой приход/корректировка на центральный
        $items=[];
        foreach ($changes as $c) {
            $sku=$c['sku']??null; $qty=$c['qty']??null;
            if (empty($sku) || $qty===null || !is_numeric($qty)) continue;
            $old = WbStockBalance::find()->select('quantity')->where(['company_id'=>$companyId,'warehouseId'=>$targetId,'sku'=>$sku])->scalar();
            $old = $old===false?0:(int)$old;
            $delta = (int)round((float)$qty) - $old;
            if ($delta!==0) $items[]=['sku'=>$sku,'delta'=>$delta];
        }
        if (empty($items)) return ['success'=>true,'processed'=>0,'errors'=>[]];
        $res = StockService::apply($companyId, $targetId, 'fbs_central_adjust', null, $items, Yii::$app->user->id);
        if (!$res['ok']) return ['success'=>false,'error'=>$res['error']];
        return ['success'=>true,'processed'=>count($items),'errors'=>[]];
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
            $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_central'=>1])->scalar();
        $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        if (!$fbsId) return ['success'=>false,'error'=>'Нет оперативного склада is_fbs'];
        $oldQty = WbStockBalance::find()->select('quantity')->where(['company_id'=>$companyId,'warehouseId'=>$fbsId,'sku'=>$sku])->scalar();
        $oldQty = $oldQty===false?0:(int)$oldQty;
        if ($oldQty===0) return ['success'=>true];
        // возврат на центральный
        $res = StockService::transfer($companyId, $fbsId, $centralId, 'fbs_virtual_delete', null, [['sku'=>$sku,'qty'=>$oldQty]], Yii::$app->user->id);
        if (!$res['ok']) return ['success'=>false,'error'=>$res['error']];
        return ['success' => true];
    }

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
        $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        if (!$fbsId) return ['success'=>false,'error'=>'Нет оперативного склада is_fbs'];
        $stock = WbStockBalance::find()->where(['company_id' => $companyId, 'warehouseId'=>$fbsId, 'sku' => $sku])->one();
        if (!$stock) {
            if ($amountRaw === null || $amountRaw === '' || !is_numeric($amountRaw)) {
                return ['success' => false, 'error' => 'Остаток не найден — сначала Сохранить или введите количество'];
            }
            $size = WbCardSize::findOne(['sku' => $sku]);
            if (!$size) {
                return ['success' => false, 'error' => "SKU $sku не найден в wbcards_sizes"];
            }
            // создаем через transfer с центрального если есть, иначе прямой apply
            $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_central'=>1])->scalar();
            $qty = (int)$amountRaw;
            $centralQty = $centralId ? (WbStockBalance::find()->select('quantity')->where(['company_id'=>$companyId,'warehouseId'=>$centralId,'sku'=>$sku])->scalar() ?: 0) : 0;
            if ($centralId && $centralQty >= $qty) {
                $res = StockService::transfer($companyId, $centralId, $fbsId, 'fbs_virtual_upload', null, [['sku'=>$sku,'qty'=>$qty]], Yii::$app->user->id);
                if (!$res['ok']) return ['success'=>false,'error'=>$res['error']];
                $stock = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$fbsId,'sku'=>$sku])->one();
            } else {
                $stock = new WbStockBalance();
                $stock->company_id = $companyId;
                $stock->warehouseId = $fbsId;
                $stock->sku = $sku;
                $stock->nmID = $size->nmID;
                $stock->chrtID = $size->chrtID;
                $stock->quantity = (int)$amountRaw;
                Yii::$app->db->createCommand()->upsert('wb_stock_balance', [
                    'company_id' => $companyId, 'warehouseId'=>$fbsId, 'sku' => $sku, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID, 'quantity' => (int)$amountRaw,
                ], ['quantity' => (int)$amountRaw, 'nmID' => $size->nmID, 'chrtID' => $size->chrtID])->execute();
                $stock = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$fbsId,'sku'=>$sku])->one();
            }
        } elseif ($amountRaw !== null && $amountRaw !== '' && is_numeric($amountRaw)) {
            // если в инпуте другое значение чем в БД — делаем transfer разницу
            $newQty = (int)$amountRaw;
            $delta = $newQty - (int)$stock->quantity;
            if ($delta !== 0) {
                $centralId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_central'=>1])->scalar();
                if ($delta>0) {
                    $res = StockService::transfer($companyId, $centralId, $fbsId, 'fbs_virtual_upload', null, [['sku'=>$sku,'qty'=>$delta]], Yii::$app->user->id);
                } else {
                    $res = StockService::transfer($companyId, $fbsId, $centralId, 'fbs_virtual_upload', null, [['sku'=>$sku,'qty'=> -$delta]], Yii::$app->user->id);
                }
                if (!$res['ok']) return ['success'=>false,'error'=>$res['error']];
                $stock = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$fbsId,'sku'=>$sku])->one();
            }
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
        if (Yii::$app->session->isActive) Yii::$app->session->close();
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

    public function actionUploadAll()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $isTest = (bool)Yii::$app->request->post('test');
        $companyId = Yii::$app->companyManager->getCurrentId();
        if (Yii::$app->companyManager->isGlobalMode()) {
            $companyId = $companyId ?: (new Query())->select('id')->from('companies')->where(['is_active' => 1])->scalar();
        }
        $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        if (!$fbsId) return ['success'=>false,'error'=>'Нет оперативного склада is_fbs'];
        $stocks = WbStockBalance::find()->where(['company_id' => $companyId, 'warehouseId'=>$fbsId])->all();
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
        if (Yii::$app->session->isActive) Yii::$app->session->close();
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
                    $size = WbCardSize::findOne(['sku' => $ps['sku'] ?? null]);
                    // keep cache
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
