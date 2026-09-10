<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use yii\web\UploadedFile;
use yii\db\Query;
use app\models\WbDoc;
use app\models\WbDocItem;
use app\models\OurWarehouse;
use app\models\WbCardSize;
use app\models\WbStockBalance;
use app\services\StockService;

/**
 * Документы склада: приход/расход
 */
class WbDocController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow'=>true,'roles'=>['manageFbsStocks']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'post' => ['post'],
                    'cancel' => ['post'],
                    'delete' => ['post'],
                    'parse' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex($type = null)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $query = WbDoc::find()->where(['company_id'=>$companyId])->orderBy(['date'=>SORT_DESC,'id'=>SORT_DESC]);
        if ($type) $query->andWhere(['type'=>$type]);
        $dataProvider = new ActiveDataProvider(['query'=>$query,'pagination'=>['pageSize'=>20]]);
        return $this->render('index', ['dataProvider'=>$dataProvider,'type'=>$type]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        // предзагрузка состава с карточками одним запросом (убирает N+1 и ускоряет posted)
        $itemsRaw = (new Query())
            ->select(['di.sku','di.nmID','di.chrtID','di.qty','di.qty_before','di.qty_fact','di.price','s.nmID as s_nmID','c.vendorCode','c.title'])
            ->from(['di'=>'{{%wb_doc_item}}'])
            ->leftJoin(['s'=>'{{wbcards_sizes}}'], 's.sku=di.sku')
            ->leftJoin(['c'=>'{{wbcards}}'], 'c.nmID=s.nmID')
            ->where(['di.doc_id'=>$model->id])
            ->orderBy(['di.id'=>SORT_ASC])
            ->all();
        // для GridView-экспорта нужен DataProvider
        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels'=>$itemsRaw,
            'pagination'=>false,
        ]);
        return $this->render('view', ['model'=>$model,'itemsRaw'=>$itemsRaw,'dataProvider'=>$dataProvider]);
    }

    public function actionCreate($type = WbDoc::TYPE_RECEIPT)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->all();
        $model = new WbDoc();
        $model->company_id = $companyId;
        $model->type = $type;
        $model->status = WbDoc::STATUS_DRAFT;
        // для TRANSFER нужны два склада, для остальных один
        if ($type === WbDoc::TYPE_TRANSFER) {
            $model->warehouseId = Yii::$app->request->get('warehouseId') ?? ($warehouses[0]->id ?? null);
            $model->to_warehouseId = Yii::$app->request->get('to_warehouseId') ?? ($warehouses[1]->id ?? $warehouses[0]->id ?? null);
        } else {
            $model->warehouseId = Yii::$app->request->get('warehouseId') ?? ($warehouses[0]->id ?? null);
        }
        $model->user_id = Yii::$app->user->id;
        if (empty($model->date)) $model->date = date('Y-m-d H:i:s');

        if ($model->load(Yii::$app->request->post())) {
            // normalize datetime-local 2026-09-09T14:30 -> 2026-09-09 14:30:00
            if (!empty($model->date)) {
                $model->date = str_replace('T',' ', $model->date);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $model->date)) $model->date .= ':00';
            }
            if (!$model->validate()) {
                return $this->render('create', ['model'=>$model,'warehouses'=>$warehouses]);
            }
            if ($model->type === WbDoc::TYPE_TRANSFER && $model->warehouseId == $model->to_warehouseId) {
                Yii::$app->session->setFlash('error','Склады "откуда" и "куда" должны отличаться');
                return $this->render('create', ['model'=>$model,'warehouses'=>$warehouses]);
            }
            $model->save(false);
            $items = Yii::$app->request->post('items', []);
            foreach ($items as $it) {
                $sku = $it['sku'] ?? null;
                if (empty($sku)) continue;
                // для инвентаризации фактом является qty_fact
                if ($model->type === WbDoc::TYPE_INVENTORY) {
                    $qtyFact = isset($it['qty_fact']) ? (int)$it['qty_fact'] : (isset($it['qty']) ? (int)$it['qty'] : 0);
                    $qtyBefore = isset($it['qty_before']) ? (int)$it['qty_before'] : StockService::getBalance($model->company_id, $model->warehouseId, $sku);
                    // пропускаем пустые строки без факта? но 0 — валидный факт (прочерк)
                    if (($it['qty_fact'] ?? '') === '' && ($it['qty'] ?? '') === '') continue;
                    if (($it['qty_fact'] ?? '') === '' && ($it['qty'] ?? '') !== '0' && ($it['qty_fact'] ?? null) !== '0') {
                        // уже пропущено выше, но для hasAttribute случая
                    }
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = $qtyFact; // для совместимости
                    if ($item->hasAttribute('qty_fact')) $item->qty_fact = $qtyFact;
                    if ($item->hasAttribute('qty_before')) $item->qty_before = $qtyBefore;
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                } elseif ($model->type === WbDoc::TYPE_ADJUSTMENT) {
                    if (!isset($it['qty']) || $it['qty']==='' || $it['qty']===null) continue;
                    $delta = (int)$it['qty'];
                    if ($delta===0) continue;
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = $delta; // подписанная дельта
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                } else {
                    if (empty($it['qty'])) continue;
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = (int)$it['qty'];
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                }
            }
            return $this->redirect(['view','id'=>$model->id]);
        }
        return $this->render('create', ['model'=>$model,'warehouses'=>$warehouses]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if (!in_array($model->status, [WbDoc::STATUS_DRAFT, WbDoc::STATUS_CANCELED], true)) {
            Yii::$app->session->setFlash('error','Редактировать можно только черновик или отмененный');
            return $this->redirect(['view','id'=>$id]);
        }
        $companyId = Yii::$app->companyManager->getCurrentId();
        $warehouses = OurWarehouse::find()->where(['company_id'=>$companyId,'is_active'=>1])->all();
        // для нормализации даты в форме
        if (Yii::$app->request->isGet && !empty($model->date)) {
            // оставляем как есть, в виде отрендерится через dateVal
        }
        if ($model->load(Yii::$app->request->post())) {
            if (!empty($model->date)) {
                $model->date = str_replace('T',' ', $model->date);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $model->date)) $model->date .= ':00';
            }
            if (!$model->validate()) {
                return $this->render('update', ['model'=>$model,'warehouses'=>$warehouses]);
            }
            if ($model->type === WbDoc::TYPE_TRANSFER && $model->warehouseId == $model->to_warehouseId) {
                Yii::$app->session->setFlash('error','Склады "откуда" и "куда" должны отличаться');
                return $this->render('update', ['model'=>$model,'warehouses'=>$warehouses]);
            }
            // если правим отмененный — возвращаем в черновики (сторно уже откатило остатки)
            if ($model->status === WbDoc::STATUS_CANCELED) $model->status = WbDoc::STATUS_DRAFT;
            $model->save(false);
            // пересоздаем состав
            WbDocItem::deleteAll(['doc_id'=>$model->id]);
            $items = Yii::$app->request->post('items', []);
            foreach ($items as $it) {
                $sku = $it['sku'] ?? null;
                if (empty($sku)) continue;
                if ($model->type === WbDoc::TYPE_INVENTORY) {
                    $qtyFact = isset($it['qty_fact']) ? (int)$it['qty_fact'] : (isset($it['qty']) ? (int)$it['qty'] : 0);
                    $qtyBefore = isset($it['qty_before']) ? (int)$it['qty_before'] : StockService::getBalance($model->company_id, $model->warehouseId, $sku);
                    if (($it['qty_fact'] ?? '') === '' && ($it['qty'] ?? '') === '') continue;
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = $qtyFact;
                    if ($item->hasAttribute('qty_fact')) $item->qty_fact = $qtyFact;
                    if ($item->hasAttribute('qty_before')) $item->qty_before = $qtyBefore;
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                } elseif ($model->type === WbDoc::TYPE_ADJUSTMENT) {
                    if (!isset($it['qty']) || $it['qty']==='' || $it['qty']===null) continue;
                    $delta = (int)$it['qty'];
                    if ($delta===0) continue;
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = $delta;
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                } else {
                    if (($it['qty'] ?? '') === '' || $it['qty'] === null) continue;
                    $size = WbCardSize::findOne(['sku'=>$sku]);
                    $item = new WbDocItem();
                    $item->doc_id = $model->id;
                    $item->sku = $sku;
                    $item->qty = (int)$it['qty'];
                    $item->nmID = $size->nmID ?? null;
                    $item->chrtID = $size->chrtID ?? null;
                    $item->price = $it['price'] ?? null;
                    $item->save(false);
                }
            }
            Yii::$app->session->setFlash('success','Документ обновлен');
            return $this->redirect(['view','id'=>$model->id]);
        }
        return $this->render('update', ['model'=>$model,'warehouses'=>$warehouses]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if (!in_array($model->status, [WbDoc::STATUS_DRAFT, WbDoc::STATUS_CANCELED], true)) {
            Yii::$app->session->setFlash('error','Удалить можно только черновик/отмененный');
            return $this->redirect(['view','id'=>$id]);
        }
        WbDocItem::deleteAll(['doc_id'=>$model->id]);
        $model->delete();
        Yii::$app->session->setFlash('success','Черновик удален');
        return $this->redirect(['index']);
    }

    public function actionPost($id)
    {
        $model = $this->findModel($id);
        if ($model->status !== WbDoc::STATUS_DRAFT) {
            Yii::$app->session->setFlash('error','Только черновик можно провести');
            return $this->redirect(['view','id'=>$id]);
        }
        if ($model->type === WbDoc::TYPE_TRANSFER) {
            $items = [];
            foreach ($model->items as $it) $items[] = ['sku'=>$it->sku,'qty'=>(int)$it->qty];
            $res = StockService::transfer($model->company_id, $model->warehouseId, $model->to_warehouseId, $model->type, $model->id, $items, Yii::$app->user->id);
        } elseif ($model->type === WbDoc::TYPE_INVENTORY) {
            $items = [];
            foreach ($model->items as $it) {
                $hasFact = $it->hasAttribute('qty_fact');
                $hasBefore = $it->hasAttribute('qty_before');
                $fact = ($hasFact && $it->qty_fact !== null) ? (int)$it->qty_fact : (int)$it->qty;
                $before = ($hasBefore && $it->qty_before !== null) ? (int)$it->qty_before : StockService::getBalance($model->company_id, $model->warehouseId, $it->sku);
                $delta = $fact - $before;
                if ($delta !== 0) $items[] = ['sku'=>$it->sku,'delta'=>$delta];
            }
            if (empty($items)) {
                $model->status = WbDoc::STATUS_POSTED;
                $model->save(false);
                Yii::$app->session->setFlash('success','Инвентаризация проведена — расхождений нет');
                return $this->redirect(['view','id'=>$id]);
            }
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type, $model->id, $items, Yii::$app->user->id);
        } elseif ($model->type === WbDoc::TYPE_ADJUSTMENT) {
            $items = [];
            foreach ($model->items as $it) $items[] = ['sku'=>$it->sku,'delta'=>(int)$it->qty];
            if (empty($items)) {
                Yii::$app->session->setFlash('error','Нет строк для корректировки');
                return $this->redirect(['view','id'=>$id]);
            }
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type, $model->id, $items, Yii::$app->user->id);
        } else {
            $items = [];
            foreach ($model->items as $it) {
                $delta = $model->type === WbDoc::TYPE_RECEIPT ? (int)$it->qty : -(int)$it->qty;
                $items[] = ['sku'=>$it->sku,'delta'=>$delta];
            }
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type, $model->id, $items, Yii::$app->user->id);
        }
        if (!$res['ok']) {
            Yii::$app->session->setFlash('error', $res['error']);
            return $this->redirect(['view','id'=>$id]);
        }
        $model->status = WbDoc::STATUS_POSTED;
        $model->save(false);
        Yii::$app->session->setFlash('success','Документ проведен');
        return $this->redirect(['view','id'=>$id]);
    }

    public function actionCancel($id)
    {
        $model = $this->findModel($id);
        if ($model->status !== WbDoc::STATUS_POSTED) {
            Yii::$app->session->setFlash('error','Только проведенный можно отменить');
            return $this->redirect(['view','id'=>$id]);
        }
        if ($model->type === WbDoc::TYPE_TRANSFER) {
            if (empty($model->to_warehouseId)) {
                Yii::$app->session->setFlash('error','У перемещения не указан склад "куда"');
                return $this->redirect(['view','id'=>$id]);
            }
            $items = [];
            foreach ($model->items as $it) $items[] = ['sku'=>$it->sku,'qty'=>(int)$it->qty];
            // сторно — обратное перемещение: было from→to, делаем to→from
            $res = StockService::transfer($model->company_id, $model->to_warehouseId, $model->warehouseId, $model->type.'_CANCEL', $model->id, $items, Yii::$app->user->id);
        } elseif ($model->type === WbDoc::TYPE_INVENTORY) {
            $items = [];
            foreach ($model->items as $it) {
                $hasFact = $it->hasAttribute('qty_fact');
                $hasBefore = $it->hasAttribute('qty_before');
                $fact = ($hasFact && $it->qty_fact !== null) ? (int)$it->qty_fact : (int)$it->qty;
                $before = ($hasBefore && $it->qty_before !== null) ? (int)$it->qty_before : 0;
                $delta = $fact - $before;
                if ($delta !== 0) $items[] = ['sku'=>$it->sku,'delta'=> -$delta]; // сторно
            }
            if (empty($items)) {
                $model->status = WbDoc::STATUS_CANCELED;
                $model->save(false);
                Yii::$app->session->setFlash('success','Инвентаризация отменена — расхождений не было');
                return $this->redirect(['view','id'=>$id]);
            }
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type.'_CANCEL', $model->id, $items, Yii::$app->user->id);
        } elseif ($model->type === WbDoc::TYPE_ADJUSTMENT) {
            $items = [];
            foreach ($model->items as $it) $items[] = ['sku'=>$it->sku,'delta'=> -(int)$it->qty];
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type.'_CANCEL', $model->id, $items, Yii::$app->user->id);
        } else {
            $items = [];
            foreach ($model->items as $it) {
                $delta = $model->type === WbDoc::TYPE_RECEIPT ? -(int)$it->qty : (int)$it->qty;
                $items[] = ['sku'=>$it->sku,'delta'=>$delta];
            }
            $res = StockService::apply($model->company_id, $model->warehouseId, $model->type.'_CANCEL', $model->id, $items, Yii::$app->user->id);
        }
        if (!$res['ok']) {
            Yii::$app->session->setFlash('error', $res['error']);
            return $this->redirect(['view','id'=>$id]);
        }
        $model->status = WbDoc::STATUS_CANCELED;
        $model->save(false);
        Yii::$app->session->setFlash('success','Документ отменен (сторно)');
        return $this->redirect(['view','id'=>$id]);
    }

    // Парсит Excel как в wb-fbs-virtual: Баркод/vendorCode/nmID + Количество
    public function actionParse()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $file = UploadedFile::getInstanceByName('file');
        if (!$file) return ['success'=>false,'error'=>'Файл не передан'];
        $tmp = Yii::getAlias('@runtime').'/doc_import_'.uniqid().'.'.$file->extension;
        $file->saveAs($tmp);
        $matched=[]; $skipped=[];
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet()->toArray(null,true,true,false);
            if (empty($sheet)) return ['success'=>false,'error'=>'Файл пустой'];
            $header = array_map(fn($h)=>mb_strtolower(trim((string)$h)), $sheet[0]);
            $colSku = $this->findColumn($header, ['баркод','barcode','sku','штрихкод']);
            $colVendor = $this->findColumn($header, ['артикул продавца','vendorcode','vendor_code','артикул']);
            $colNmId = $this->findColumn($header, ['nmid','nm_id','nm id','артикул wb','nm']);
            $colQty = $this->findColumn($header, ['количество','qty','quantity','кол-во','остаток']);
            if ($colQty===null || ($colSku===null && $colVendor===null && $colNmId===null)) {
                return ['success'=>false,'error'=>'Не найдены колонки. Нужны: Баркод и/или Артикул + Количество'];
            }
            $companyId = Yii::$app->companyManager->getCurrentId();
            for ($i=1;$i<count($sheet);$i++) {
                $row=$sheet[$i];
                $sku = $colSku!==null ? trim((string)($row[$colSku]??'')) : null;
                $vendor = $colVendor!==null ? trim((string)($row[$colVendor]??'')) : null;
                $nmRaw = $colNmId!==null ? trim((string)($row[$colNmId]??'')) : null;
                $qtyRaw = $row[$colQty] ?? null;
                if ($qtyRaw===null || $qtyRaw==='' || !is_numeric($qtyRaw)) continue;
                $resolved = $this->resolveSku($sku,$vendor,$nmRaw,$companyId);
                if (!$resolved) { $skipped[]="Строка ".($i+1).": не найден (sku=$sku vendor=$vendor nmID=$nmRaw)"; continue; }
                $matched[]=['sku'=>$resolved['sku'],'nmID'=>$resolved['nmID'],'chrtID'=>$resolved['chrtID'],'qty'=>(int)round((float)$qtyRaw)];
            }
        } finally { @unlink($tmp); }
        return ['success'=>true,'matched'=>$matched,'skipped'=>$skipped];
    }

    public function actionGetBalance($sku, $warehouseId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $companyId = Yii::$app->companyManager->getCurrentId();
        $qty = WbStockBalance::findOne(['company_id'=>$companyId,'warehouseId'=>(int)$warehouseId,'sku'=>$sku]);
        return ['qty'=>$qty ? (int)$qty->quantity : 0];
    }

    public function actionInventoryData($warehouseId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $companyId = Yii::$app->companyManager->getCurrentId();
        // все товары компании + их баланс на складе
        $rows = (new Query())
            ->select(['s.sku','s.nmID','s.chrtID','c.vendorCode','c.title','b.quantity'])
            ->from(['s'=>'wbcards_sizes'])
            ->innerJoin(['c'=>'wbcards'], 'c.nmID=s.nmID')
            ->leftJoin(['b'=>'wb_stock_balance'], 'b.sku=s.sku AND b.company_id=:cid AND b.warehouseId=:wid', [':cid'=>$companyId, ':wid'=>(int)$warehouseId])
            ->where(['c.company_id'=>$companyId])
            ->orderBy(['c.vendorCode'=>SORT_ASC, 's.sku'=>SORT_ASC])
            ->all();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'sku'=>$r['sku'],'nmID'=>$r['nmID'],'chrtID'=>$r['chrtID'],
                'vendorCode'=>$r['vendorCode'],'title'=>$r['title'],
                'qty_before'=> (int)($r['quantity'] ?? 0),
            ];
        }
        return $out;
    }

    public function actionPrint($id)
    {
        $model = $this->findModel($id);
        $this->layout = false;
        return $this->render('print', ['model'=>$model]);
    }

    public function actionSearchProduct($term)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $companyId = Yii::$app->companyManager->getCurrentId();
        $term = trim($term);
        if (mb_strlen($term)<2) return [];
        $q = (new Query())->select(['s.sku','s.nmID','s.chrtID','c.vendorCode','c.title','c.brand'])
            ->from(['s'=>'wbcards_sizes'])->innerJoin(['c'=>'wbcards'],'c.nmID=s.nmID')
            ->where(['c.company_id'=>$companyId])
            ->andWhere(['or',['like','s.sku',$term],['like','c.vendorCode',$term],['like','c.title',$term]])->limit(20)->all();
        return array_map(fn($r)=>[
            'sku'=>$r['sku'],'vendorCode'=>$r['vendorCode'],'title'=>$r['title'],'nmID'=>$r['nmID'],'chrtID'=>$r['chrtID'],
            'label'=>$r['vendorCode'].' — '.$r['title'].' ('.$r['sku'].')'
        ], $q);
    }

    private function findColumn(array $header, array $variants): ?int
    {
        foreach ($header as $idx=>$v) if (in_array($v,$variants,true)) return $idx;
        return null;
    }
    private function resolveSku($sku,$vendorCode,$nmIdRaw,$companyId)
    {
        if (!empty($sku)) { $r=(new Query())->select(['sku','nmID','chrtID'])->from('wbcards_sizes')->where(['sku'=>$sku])->one(); if($r) return $r; }
        if (!empty($nmIdRaw) && ctype_digit($nmIdRaw)) {
            $card=\app\models\WbCard::find()->where(['nmID'=>(int)$nmIdRaw])->one();
            if($card){ $s=\app\models\WbCardSize::find()->where(['nmID'=>(int)$nmIdRaw])->one(); if($s) return ['sku'=>$s->sku,'nmID'=>$s->nmID,'chrtID'=>$s->chrtID]; }
        }
        if (!empty($vendorCode)) {
            $q=\app\models\WbCard::find()->where(['vendorCode'=>trim($vendorCode)]);
            if($companyId) $q->andWhere(['company_id'=>$companyId]);
            $card=$q->one();
            if($card){ $s=\app\models\WbCardSize::find()->where(['nmID'=>$card->nmID])->one(); if($s){ $cnt=\app\models\WbCardSize::find()->where(['nmID'=>$card->nmID])->count(); if($cnt==1) return ['sku'=>$s->sku,'nmID'=>$s->nmID,'chrtID'=>$s->chrtID]; } }
        }
        return null;
    }

    protected function findModel($id)
    {
        $companyId = Yii::$app->companyManager->getCurrentId();
        $model = WbDoc::findOne(['id'=>$id,'company_id'=>$companyId]);
        if (!$model) throw new \yii\web\NotFoundHttpException('Документ не найден');
        return $model;
    }
}
