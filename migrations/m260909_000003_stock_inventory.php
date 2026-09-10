<?php
use yii\db\Migration;

/**
 * Инвентаризация: доп.поля для wb_doc_item чтобы хранить факт/до/дельта
 */
class m260909_000003_stock_inventory extends Migration
{
    public function safeUp()
    {
        $table = $this->db->getTableSchema('{{%wb_doc_item}}', true);
        if ($table && !$table->getColumn('qty_before')) {
            $this->addColumn('{{%wb_doc_item}}', 'qty_before', $this->integer()->null()->comment('остаток по учету на момент создания инвентаризации')->after('qty'));
        }
        if ($table && !$table->getColumn('qty_fact')) {
            $this->addColumn('{{%wb_doc_item}}', 'qty_fact', $this->integer()->null()->comment('факт по инвентаризации')->after('qty_before'));
        }
        // для pdf/акт можно хранить delta, но вычисляется
        echo "  wb_doc_item qty_before/qty_fact added for INVENTORY\n";
    }

    public function safeDown()
    {
        $table = $this->db->getTableSchema('{{%wb_doc_item}}', true);
        if ($table && $table->getColumn('qty_fact')) $this->dropColumn('{{%wb_doc_item}}', 'qty_fact');
        if ($table && $table->getColumn('qty_before')) $this->dropColumn('{{%wb_doc_item}}', 'qty_before');
        echo "  rolled back\n";
    }
}
