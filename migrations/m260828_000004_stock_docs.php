<?php
use yii\db\Migration;

/**
 * Документы склада: приход/расход
 */
class m260828_000004_stock_docs extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%wb_doc}}', [
            'id' => $this->primaryKey(),
            'company_id' => $this->integer()->notNull(),
            'type' => $this->string(20)->notNull()->comment('RECEIPT/EXPENSE'),
            'status' => $this->string(20)->notNull()->defaultValue('draft')->comment('draft/posted/canceled'),
            'warehouseId' => $this->bigInteger()->notNull()->comment('склад документа'),
            'date' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'comment' => $this->string(500)->null(),
            'user_id' => $this->integer()->null(),
            'created_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_wb_doc_company', '{{%wb_doc}}', ['company_id','type','status']);
        $this->addForeignKey('fk_wb_doc_company', '{{%wb_doc}}', 'company_id', '{{%companies}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%wb_doc_item}}', [
            'id' => $this->primaryKey(),
            'doc_id' => $this->integer()->notNull(),
            'sku' => $this->string(50)->notNull(),
            'nmID' => $this->integer()->null(),
            'chrtID' => $this->bigInteger()->null(),
            'qty' => $this->integer()->notNull()->comment('количество, всегда >0, знак дает doc.type'),
            'price' => $this->decimal(10,2)->null()->comment('цена для прихода'),
        ]);
        $this->createIndex('idx_wb_doc_item_doc', '{{%wb_doc_item}}', 'doc_id');
        $this->addForeignKey('fk_wb_doc_item_doc', '{{%wb_doc_item}}', 'doc_id', '{{%wb_doc}}', 'id', 'CASCADE', 'CASCADE');

        echo "  wb_doc + wb_doc_item created\n";
    }

    public function safeDown()
    {
        $this->dropTable('{{%wb_doc_item}}');
        $this->dropTable('{{%wb_doc}}');
        echo "  rolled back\n";
    }
}
