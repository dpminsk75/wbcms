<?php
use yii\db\Migration;

class m260901_000007_seo_targets extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%wb_seo_target}}', [
            'id' => $this->primaryKey(),
            'nmID' => $this->integer()->notNull()->comment('FK wbcards.nmID'),
            'phrase' => $this->string(500)->notNull(),
            'priority' => $this->integer()->notNull()->defaultValue(10),
            'is_active' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'added_by' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_seo_target_nm', '{{%wb_seo_target}}', 'nmID');
        $this->createIndex('uq_seo_target_nm_phrase', '{{%wb_seo_target}}', ['nmID','phrase'], true);
        $this->addForeignKey('fk_seo_target_card', '{{%wb_seo_target}}', 'nmID', '{{%wbcards}}', 'nmID', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_seo_target_user', '{{%wb_seo_target}}', 'added_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
    }
    public function safeDown()
    {
        $this->dropForeignKey('fk_seo_target_user', '{{%wb_seo_target}}');
        $this->dropForeignKey('fk_seo_target_card', '{{%wb_seo_target}}');
        $this->dropTable('{{%wb_seo_target}}');
    }
}
