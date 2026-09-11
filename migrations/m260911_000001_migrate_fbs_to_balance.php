<?php
use yii\db\Migration;

class m260911_000001_migrate_fbs_to_balance extends Migration
{
    public function safeUp()
    {
        // our_warehouse должен иметь is_central/is_fbs, добавляем consider_orders если нет
        $ow = $this->db->getTableSchema('{{%our_warehouse}}', true);
        if ($ow && !$ow->getColumn('consider_orders')) {
            $this->addColumn('{{%our_warehouse}}', 'consider_orders', $this->boolean()->notNull()->defaultValue(0)->comment('учитывать заказы для is_fbs')->after('is_fbs'));
            echo "  our_warehouse.consider_orders added\n";
        }
        // перенос consider_orders из wb_fbs_warehouse если есть
        if ($this->db->getTableSchema('{{%wb_fbs_warehouse}}', true) && $ow && $ow->getColumn('consider_orders')) {
            $this->execute("
                UPDATE {{%our_warehouse}} ow
                JOIN (
                    SELECT company_id, MAX(consider_orders) as co FROM {{%wb_fbs_warehouse}} WHERE consider_orders=1 GROUP BY company_id
                ) fw ON fw.company_id=ow.company_id
                SET ow.consider_orders=1
                WHERE ow.is_fbs=1
            ");
            echo "  consider_orders migrated\n";
        }

        // перенос wb_central_stock → wb_stock_balance (центральный физический)
        if ($this->db->getTableSchema('{{%wb_central_stock}}', true)) {
            $this->execute("
                INSERT INTO {{%wb_stock_balance}} (company_id, warehouseId, sku, nmID, chrtID, quantity)
                SELECT c.company_id,
                       (SELECT id FROM {{%our_warehouse}} WHERE company_id=c.company_id AND is_central=1 LIMIT 1) as wid,
                       c.sku, c.nmID, c.chrtID, c.quantity
                FROM {{%wb_central_stock}} c
                WHERE (SELECT id FROM {{%our_warehouse}} WHERE company_id=c.company_id AND is_central=1 LIMIT 1) IS NOT NULL
                ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), nmID=VALUES(nmID), chrtID=VALUES(chrtID)
            ");
            echo "  wb_central_stock -> wb_stock_balance migrated\n";
        }
        // перенос wb_virtual_stock → wb_stock_balance (оперативный is_fbs физический)
        if ($this->db->getTableSchema('{{%wb_virtual_stock}}', true)) {
            $this->execute("
                INSERT INTO {{%wb_stock_balance}} (company_id, warehouseId, sku, nmID, chrtID, quantity)
                SELECT c.company_id,
                       (SELECT id FROM {{%our_warehouse}} WHERE company_id=c.company_id AND is_fbs=1 LIMIT 1) as wid,
                       c.sku, c.nmID, c.chrtID, c.quantity
                FROM {{%wb_virtual_stock}} c
                WHERE (SELECT id FROM {{%our_warehouse}} WHERE company_id=c.company_id AND is_fbs=1 LIMIT 1) IS NOT NULL
                ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), nmID=VALUES(nmID), chrtID=VALUES(chrtID)
            ");
            echo "  wb_virtual_stock -> wb_stock_balance migrated\n";
        }
        // старые таблицы не дропаем сейчас — дроп после теста п.6
        echo "  migrate done (old tables kept for rollback)\n";
    }

    public function safeDown()
    {
        echo "  no down — manual restore needed\n";
        return false;
    }
}
