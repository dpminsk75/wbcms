<?php
namespace app\services;

use Yii;
use yii\db\Query;

class StockReportService
{
    public static function balance(int $companyId, $warehouseId, ?string $date, ?string $q, bool $onlyAvailable=false): array
    {
        $isHistory = $date && strtotime($date) < strtotime(date('Y-m-d 00:00:00'));
        $params = [':cid'=>$companyId];
        $whFilter = '';
        if ($warehouseId && $warehouseId !== 'all') {
            $whFilter = ' AND b.warehouseId = :wid';
            $params[':wid'] = (int)$warehouseId;
        }
        $searchWhere = '';
        if (!empty($q)) {
            $searchWhere = ' AND (s.sku LIKE :q OR c.vendorCode LIKE :q2 OR c.title LIKE :q3 OR CAST(c.nmID AS CHAR) LIKE :q4 OR CAST(s.nmID AS CHAR) LIKE :q5)';
            $params[':q'] = '%'.$q.'%';
            $params[':q2'] = '%'.$q.'%';
            $params[':q3'] = '%'.$q.'%';
            $params[':q4'] = '%'.$q.'%';
            $params[':q5'] = '%'.$q.'%';
        }
        if ($isHistory) {
            $sql = "
                SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title, COALESCE(SUM(l.qty_delta),0) as quantity, l.warehouseId as wid
                FROM {{wbcards_sizes}} s
                INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
                LEFT JOIN {{wb_stock_ledger}} l ON l.sku=s.sku AND l.company_id=:cid AND l.created_at <= :dt {$whFilter}
                WHERE c.company_id=:cid {$searchWhere}
                GROUP BY s.sku, c.vendorCode, c.title, l.warehouseId
                HAVING quantity != 0 OR l.warehouseId IS NULL
                ORDER BY c.vendorCode ASC, s.sku ASC
            ";
            $params[':dt'] = date('Y-m-d H:i:s', strtotime($date));
            if ($whFilter === '') {
                $sql = "
                    SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title, COALESCE(SUM(l.qty_delta),0) as quantity, 0 as wid
                    FROM {{wbcards_sizes}} s
                    INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
                    LEFT JOIN {{wb_stock_ledger}} l ON l.sku=s.sku AND l.company_id=:cid AND l.created_at <= :dt
                    WHERE c.company_id=:cid {$searchWhere}
                    GROUP BY s.sku, c.vendorCode, c.title
                    HAVING quantity != 0
                    ORDER BY c.vendorCode ASC
                ";
            }
            $rows = Yii::$app->db->createCommand($sql, $params)->queryAll();
            if ($onlyAvailable) $rows = array_values(array_filter($rows, fn($r)=>(int)($r['quantity']??0)>0));
            return $rows;
        } else {
            if ($whFilter === '') {
                $sql = "
                    SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title, b.warehouseId as wid, w.name as warehouseName, COALESCE(b.quantity,0) as quantity
                    FROM {{wbcards_sizes}} s
                    INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
                    LEFT JOIN {{wb_stock_balance}} b ON b.sku=s.sku AND b.company_id=:cid
                    LEFT JOIN {{our_warehouse}} w ON w.id=b.warehouseId
                    WHERE c.company_id=:cid {$searchWhere}
                    ORDER BY c.vendorCode ASC, s.sku ASC, b.warehouseId ASC
                ";
                $rows = Yii::$app->db->createCommand($sql, $params)->queryAll();
                if ($onlyAvailable) $rows = array_values(array_filter($rows, fn($r)=>(int)($r['quantity']??0)>0));
                return $rows;
            } else {
                $sql = "
                    SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title, b.warehouseId as wid, w.name as warehouseName, COALESCE(b.quantity,0) as quantity
                    FROM {{wbcards_sizes}} s
                    INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
                    LEFT JOIN {{wb_stock_balance}} b ON b.sku=s.sku AND b.company_id=:cid AND b.warehouseId=:wid
                    LEFT JOIN {{our_warehouse}} w ON w.id=b.warehouseId
                    WHERE c.company_id=:cid {$searchWhere}
                    ORDER BY c.vendorCode ASC, s.sku ASC
                ";
                $rows = Yii::$app->db->createCommand($sql, $params)->queryAll();
                if ($onlyAvailable) $rows = array_values(array_filter($rows, fn($r)=>(int)($r['quantity']??0)>0));
                return $rows;
            }
        }
    }

    public static function turnover(int $companyId, $warehouseId, string $dateFrom, string $dateTo, ?string $q): array
    {
        $params = [':cid'=>$companyId, ':d1'=>date('Y-m-d 00:00:00', strtotime($dateFrom)), ':d2'=>date('Y-m-d 23:59:59', strtotime($dateTo))];
        $wh = '';
        if ($warehouseId && $warehouseId !== 'all') { $wh = ' AND l.warehouseId=:wid'; $params[':wid']=(int)$warehouseId; }
        $search = '';
        if (!empty($q)) {
            $search = ' AND (s.sku LIKE :q OR c.vendorCode LIKE :q2 OR c.title LIKE :q3 OR CAST(c.nmID AS CHAR) LIKE :q4 OR CAST(s.nmID AS CHAR) LIKE :q5)';
            $params[':q']='%'.$q.'%'; $params[':q2']='%'.$q.'%'; $params[':q3']='%'.$q.'%'; $params[':q4']='%'.$q.'%'; $params[':q5']='%'.$q.'%';
        }
        $sql = "
            SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title,
                   COALESCE(start_t.qty,0) as startQty,
                   COALESCE(per.receipt,0) as receipt,
                   COALESCE(per.expense,0) as expense,
                   COALESCE(per.t_in,0) as t_in,
                   COALESCE(per.t_out,0) as t_out,
                   COALESCE(per.inv,0) as inv,
                   COALESCE(per.adj,0) as adj,
                   COALESCE(end_t.qty,0) as endQty
            FROM {{wbcards_sizes}} s
            INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
            LEFT JOIN (
                SELECT sku, SUM(qty_delta) as qty FROM {{wb_stock_ledger}} l WHERE l.company_id=:cid AND l.created_at < :d1 {$wh} GROUP BY sku
            ) start_t ON start_t.sku=s.sku
            LEFT JOIN (
                SELECT sku,
                    SUM(CASE WHEN l.doc_type='RECEIPT' THEN qty_delta ELSE 0 END) as receipt,
                    SUM(CASE WHEN l.doc_type='EXPENSE' THEN -qty_delta ELSE 0 END) as expense,
                    SUM(CASE WHEN l.doc_type='TRANSFER' AND qty_delta>0 THEN qty_delta ELSE 0 END) as t_in,
                    SUM(CASE WHEN l.doc_type='TRANSFER' AND qty_delta<0 THEN -qty_delta ELSE 0 END) as t_out,
                    SUM(CASE WHEN l.doc_type='INVENTORY' THEN qty_delta ELSE 0 END) as inv,
                    SUM(CASE WHEN l.doc_type='ADJUSTMENT' THEN qty_delta ELSE 0 END) as adj
                FROM {{wb_stock_ledger}} l WHERE l.company_id=:cid AND l.created_at BETWEEN :d1 AND :d2 {$wh} GROUP BY sku
            ) per ON per.sku=s.sku
            LEFT JOIN (
                SELECT sku, SUM(qty_delta) as qty FROM {{wb_stock_ledger}} l WHERE l.company_id=:cid AND l.created_at <= :d2 {$wh} GROUP BY sku
            ) end_t ON end_t.sku=s.sku
            WHERE c.company_id=:cid {$search}
            ORDER BY c.vendorCode ASC, s.sku ASC
        ";
        $rows = Yii::$app->db->createCommand($sql, $params)->queryAll();
        foreach ($rows as &$r) {
            $r['turnover'] = (int)$r['receipt'] + (int)$r['expense'] + (int)$r['t_in'] + (int)$r['t_out'] + abs((int)$r['inv']) + abs((int)$r['adj']);
        }
        $rows = array_values(array_filter($rows, fn($r)=> (int)$r['startQty']!=0 || (int)$r['endQty']!=0 || (int)$r['turnover']!=0 ));
        return $rows;
    }

    public static function card(int $companyId, string $term, $warehouseId, ?string $dateFrom, ?string $dateTo): array
    {
        $term = trim($term);
        if ($term === '') return [];
        // ищем все sku по 4 полям как в балансе/оборотке, затем показываем проводки по ним
        $skuRows = (new Query())
            ->select(['s.sku'])
            ->from(['s'=>'{{%wbcards_sizes}}'])
            ->innerJoin(['c'=>'{{%wbcards}}'],'c.nmID=s.nmID')
            ->where(['c.company_id'=>$companyId])
            ->andWhere(['or',
                ['like','s.sku',$term],
                ['like','c.vendorCode',$term],
                ['like','c.title',$term],
                ['like','CAST(c.nmID AS CHAR)',$term],
                ['like','CAST(s.nmID AS CHAR)',$term],
            ])
            ->limit(50)
            ->all();
        $skus = array_column($skuRows, 'sku');
        if (empty($skus)) {
            // fallback — если ввели точный sku которого нет в wbcards но есть в ledger (удаленная карточка) — ищем напрямую
            $skus = [$term];
        }
        $q = (new Query())
            ->select(['l.created_at','l.doc_type','l.doc_id','l.warehouseId','w.name as warehouseName','l.sku','c.vendorCode','c.title','l.qty_delta','l.qty_before','l.qty_after','u.username'])
            ->from(['l'=>'{{%wb_stock_ledger}}'])
            ->leftJoin(['w'=>'{{%our_warehouse}}'],'w.id=l.warehouseId')
            ->leftJoin(['u'=>'{{%user}}'],'u.id=l.user_id')
            ->leftJoin(['s'=>'{{%wbcards_sizes}}'],'s.sku=l.sku')
            ->leftJoin(['c'=>'{{%wbcards}}'],'c.nmID=s.nmID')
            ->where(['l.company_id'=>$companyId])
            ->andWhere(['in','l.sku',$skus])
            ->orderBy(['l.created_at'=>SORT_ASC, 'l.id'=>SORT_ASC]);
        if ($warehouseId && $warehouseId !== 'all') $q->andWhere(['l.warehouseId'=>(int)$warehouseId]);
        if ($dateFrom) $q->andWhere(['>=','l.created_at', date('Y-m-d 00:00:00', strtotime($dateFrom))]);
        if ($dateTo) $q->andWhere(['<=','l.created_at', date('Y-m-d 23:59:59', strtotime($dateTo))]);
        return $q->all();
    }

    public static function nonLiquid(int $companyId, $warehouseId, int $days, ?string $q): array
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $params = [':cid'=>$companyId, ':date'=>$date];
        $wh = '';
        if ($warehouseId && $warehouseId !== 'all') { $wh=' AND b.warehouseId=:wid'; $params[':wid']=(int)$warehouseId; }
        $search = '';
        if (!empty($q)) {
            $search=' AND (s.sku LIKE :q OR c.vendorCode LIKE :q2 OR c.title LIKE :q3 OR CAST(c.nmID AS CHAR) LIKE :q4 OR CAST(s.nmID AS CHAR) LIKE :q5)';
            $params[':q']='%'.$q.'%'; $params[':q2']='%'.$q.'%'; $params[':q3']='%'.$q.'%'; $params[':q4']='%'.$q.'%'; $params[':q5']='%'.$q.'%';
        }
        $sql = "
            SELECT s.sku, s.nmID, s.chrtID, c.vendorCode, c.title, b.warehouseId as wid, w.name as warehouseName, COALESCE(b.quantity,0) as quantity,
                   MAX(l.created_at) as last_move
            FROM {{wbcards_sizes}} s
            INNER JOIN {{wbcards}} c ON c.nmID=s.nmID AND c.company_id=:cid
            LEFT JOIN {{wb_stock_balance}} b ON b.sku=s.sku AND b.company_id=:cid {$wh}
            LEFT JOIN {{our_warehouse}} w ON w.id=b.warehouseId
            LEFT JOIN {{wb_stock_ledger}} l ON l.sku=s.sku AND l.company_id=:cid ".($warehouseId && $warehouseId!=='all' ? ' AND l.warehouseId=:wid2' : '')."
            WHERE c.company_id=:cid {$search}
            GROUP BY s.sku, c.vendorCode, c.title, b.warehouseId, b.quantity, w.name
            HAVING (last_move IS NULL OR last_move < :date) AND COALESCE(b.quantity,0) > 0
            ORDER BY last_move ASC, c.vendorCode ASC
        ";
        if ($warehouseId && $warehouseId!=='all') $params[':wid2']=(int)$warehouseId;
        return Yii::$app->db->createCommand($sql, $params)->queryAll();
    }
}
