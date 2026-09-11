<?php
use yii\db\Migration;

/**
 * Фундамент: единый остаток wb_stock_balance + журнал wb_stock_ledger + тип склада.
 */
class m260828_000003_stock_balance extends Migration
{
    public function safeUp()
    {
        // 1. wb_stock_balance — единый остаток по (company, warehouse, sku)
        $this->createTable('{{%wb_stock_balance}}', [
            'company_id' => $this->integer()->notNull()->comment('company'),
            'warehouseId' => $this->bigInteger()->notNull()->comment('WB warehouseId'),
            'sku' => $this->string(50)->notNull()->comment('баркод'),
            'nmID' => $this->integer()->null(),
            'chrtID' => $this->bigInteger()->null(),
            'quantity' => $this->integer()->notNull()->defaultValue(0),
            'updated_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);
        $this->addPrimaryKey('pk_wb_stock_balance', '{{%wb_stock_balance}}', ['company_id','warehouseId','sku']);
        $this->createIndex('idx_wb_stock_balance_nmid', '{{%wb_stock_balance}}', 'nmID');
        $this->addForeignKey('fk_wb_stock_balance_company', '{{%wb_stock_balance}}', 'company_id', '{{%companies}}', 'id', 'CASCADE', 'CASCADE');

        // 2. wb_stock_ledger — неизменяемый журнал проводок
        $this->createTable('{{%wb_stock_ledger}}', [
            'id' => $this->bigPrimaryKey(),
            'company_id' => $this->integer()->notNull(),
            'doc_type' => $this->string(30)->notNull()->comment('RECEIPT/TRANSFER/EXPENSE/INVENTORY/ADJUSTMENT/fbs_order'),
            'doc_id' => $this->bigInteger()->null()->comment('ссылка на документ'),
            'warehouseId' => $this->bigInteger()->notNull(),
            'sku' => $this->string(50)->notNull(),
            'qty_delta' => $this->integer()->notNull()->comment('дельта'),
            'qty_before' => $this->integer()->notNull(),
            'qty_after' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'created_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_wb_stock_ledger_lookup', '{{%wb_stock_ledger}}', ['company_id','warehouseId','sku']);
        $this->createIndex('idx_wb_stock_ledger_doc', '{{%wb_stock_ledger}}', ['doc_type','doc_id']);

        // 3. wb_fbs_warehouse.type — центральный/виртуальный/физический
        // колонки is_virtual/is_deleting/is_processing/consider_orders уже есть из structure.sql патча
        if (!$this->db->getTableSchema('{{%wb_fbs_warehouse}}', true)->getColumn('type')) {
            $this->addColumn('{{%wb_fbs_warehouse}}', 'type', "ENUM('central','virtual','physical') NOT NULL DEFAULT 'physical' COMMENT 'central/virtual/physical' AFTER `consider_orders`");
        }
        // миграция данных: is_virtual=1 → virtual, иначе central для первого склада компании, остальные physical — пока оставим physical, центр выберешь вручную
        $this->execute("UPDATE {{%wb_fbs_warehouse}} SET `type`='virtual' WHERE `is_virtual`=1");
        // если у компании нет central — пометим самый старый склад как central (опционально)
        // UPDATE wb_fbs_warehouse w JOIN (SELECT MIN(id) as mid, company_id FROM wb_fbs_warehouse GROUP BY company_id) m ON m.mid=w.id AND w.type='physical' SET w.type='central'

        echo "  wb_stock_balance + wb_stock_ledger + wb_fbs_warehouse.type created\n";
    }

    public function safeDown()
    {
        $this->dropTable('{{%wb_stock_ledger}}');
        $this->dropTable('{{%wb_stock_balance}}');
        $table = $this->db->getTableSchema('{{%wb_fbs_warehouse}}', true);
        if ($table && $table->getColumn('type')) {
            $this->dropColumn('{{%wb_fbs_warehouse}}', 'type');
        }
        echo "  rolled back\n";
    }
}
