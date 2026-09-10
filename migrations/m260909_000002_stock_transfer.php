<?php
use yii\db\Migration;

/**
 * Перемещение: два склада в шапке
 */
class m260909_000002_stock_transfer extends Migration
{
    public function safeUp()
    {
        // wb_doc уже есть из 000004, добавляем to_warehouseId и тип TRANSFER
        if (!$this->db->getTableSchema('{{%wb_doc}}', true)->getColumn('to_warehouseId')) {
            $this->addColumn('{{%wb_doc}}', 'to_warehouseId', $this->bigInteger()->null()->comment('куда для TRANSFER')->after('warehouseId'));
        }
        // тип TRANSFER уже входит в check range модели, в БД ENUM нет — string, ничего не надо
        echo "  wb_doc.to_warehouseId added\n";
    }

    public function safeDown()
    {
        $table = $this->db->getTableSchema('{{%wb_doc}}', true);
        if ($table && $table->getColumn('to_warehouseId')) {
            $this->dropColumn('{{%wb_doc}}', 'to_warehouseId');
        }
        echo "  rolled back\n";
    }
}
