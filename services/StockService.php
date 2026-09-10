<?php
namespace app\services;

use Yii;
use yii\db\Expression;
use app\models\WbStockBalance;
use app\models\WbStockLedger;
use app\models\WbCardSize;

/**
 * Единственное место записи остатков. Все документы идут через него.
 */
class StockService
{
    /**
     * Текущий остаток или 0.
     */
    public static function getBalance(int $companyId, int $warehouseId, string $sku): int
    {
        $row = WbStockBalance::findOne(['company_id'=>$companyId,'warehouseId'=>$warehouseId,'sku'=>$sku]);
        return $row ? (int)$row->quantity : 0;
    }

    /**
     * Применить документ: массив строк [sku, qty_delta]. qty_delta может быть + или -.
     * Атомарно в транзакции, пишет ledger, не дает уйти в минус.
     * @return array{ok:bool, error?:string}
     */
    public static function apply(int $companyId, int $warehouseId, string $docType, ?int $docId, array $items, ?int $userId = null): array
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            foreach ($items as $it) {
                $sku = $it['sku'];
                $delta = (int)$it['delta'];
                if ($delta === 0) continue;

                $size = WbCardSize::findOne(['sku'=>$sku]);
                $nmID = $size ? $size->nmID : null;
                $chrtID = $size ? $size->chrtID : null;

                // SELECT FOR UPDATE
                $balance = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$warehouseId,'sku'=>$sku])->one();
                $before = $balance ? (int)$balance->quantity : 0;
                $after = $before + $delta;
                if ($after < 0) {
                    $tx->rollBack();
                    return ['ok'=>false,'error'=>"Недостаточно на складе $warehouseId sku $sku: $before + ($delta) <0"];
                }

                if ($balance) {
                    $balance->quantity = $after;
                    $balance->nmID = $nmID;
                    $balance->chrtID = $chrtID;
                    if (!$balance->save(false)) {
                        $tx->rollBack();
                        return ['ok'=>false,'error'=>'save balance failed'];
                    }
                } else {
                    $balance = new WbStockBalance();
                    $balance->company_id = $companyId;
                    $balance->warehouseId = $warehouseId;
                    $balance->sku = $sku;
                    $balance->nmID = $nmID;
                    $balance->chrtID = $chrtID;
                    $balance->quantity = $after;
                    if (!$balance->save(false)) {
                        $tx->rollBack();
                        return ['ok'=>false,'error'=>'insert balance failed'];
                    }
                }

                $ledger = new WbStockLedger();
                $ledger->company_id = $companyId;
                $ledger->doc_type = $docType;
                $ledger->doc_id = $docId;
                $ledger->warehouseId = $warehouseId;
                $ledger->sku = $sku;
                $ledger->qty_delta = $delta;
                $ledger->qty_before = $before;
                $ledger->qty_after = $after;
                $ledger->user_id = $userId ?? (Yii::$app->user->isGuest ? null : Yii::$app->user->id);
                if (!$ledger->save(false)) {
                    $tx->rollBack();
                    return ['ok'=>false,'error'=>'ledger save failed'];
                }
            }
            $tx->commit();
            return ['ok'=>true];
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), 'stock');
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }

    /**
     * Перемещение between warehouses: две проводки в одной транзакции.
     */
    public static function transfer(int $companyId, int $fromWh, int $toWh, string $docType, ?int $docId, array $items, ?int $userId = null): array
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            // списание
            $res = self::applyInsideTx($db, $companyId, $fromWh, $docType, $docId, array_map(fn($i)=>['sku'=>$i['sku'],'delta'=> - (int)$i['qty']], $items), $userId);
            if (!$res['ok']) { $tx->rollBack(); return $res; }
            // приход
            $res = self::applyInsideTx($db, $companyId, $toWh, $docType, $docId, array_map(fn($i)=>['sku'=>$i['sku'],'delta'=> (int)$i['qty']], $items), $userId);
            if (!$res['ok']) { $tx->rollBack(); return $res; }
            $tx->commit();
            return ['ok'=>true];
        } catch (\Throwable $e) {
            $tx->rollBack();
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }

    private static function applyInsideTx($db, int $companyId, int $warehouseId, string $docType, ?int $docId, array $deltas, ?int $userId): array
    {
        foreach ($deltas as $it) {
            $sku = $it['sku']; $delta = (int)$it['delta'];
            if ($delta===0) continue;
            $size = WbCardSize::findOne(['sku'=>$sku]);
            $balance = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$warehouseId,'sku'=>$sku])->one();
            $before = $balance ? (int)$balance->quantity : 0;
            $after = $before + $delta;
            if ($after < 0) return ['ok'=>false,'error'=>"Недостаточно $sku на $warehouseId"];
            if ($balance) {
                $balance->quantity = $after;
                $balance->save(false);
            } else {
                $b = new WbStockBalance(); $b->company_id=$companyId; $b->warehouseId=$warehouseId; $b->sku=$sku; $b->nmID=$size->nmID??null; $b->chrtID=$size->chrtID??null; $b->quantity=$after; $b->save(false);
            }
            $l = new WbStockLedger(); $l->company_id=$companyId; $l->doc_type=$docType; $l->doc_id=$docId; $l->warehouseId=$warehouseId; $l->sku=$sku; $l->qty_delta=$delta; $l->qty_before=$before; $l->qty_after=$after; $l->user_id=$userId?? (Yii::$app->user->isGuest?null:Yii::$app->user->id); $l->save(false);
        }
        return ['ok'=>true];
    }
}
