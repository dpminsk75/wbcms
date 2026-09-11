<?php
use yii\db\Migration;

/**
 * Реальные склады компании (не кэш WB) с признаками центральный / для FBS-схемы
 */
class m260828_000005_our_warehouse extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%our_warehouse}}', [
            'id' => $this->primaryKey(),
            'company_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'code' => $this->string(50)->null()->comment('код для интеграции'),
            'is_central' => $this->boolean()->notNull()->defaultValue(false)->comment('центральный склад'),
            'is_fbs' => $this->boolean()->notNull()->defaultValue(false)->comment('для FBS схемы (рабочий)'),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'address' => $this->string(500)->null(),
            'created_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_our_warehouse_company', '{{%our_warehouse}}', 'company_id');
        $this->addForeignKey('fk_our_warehouse_company', '{{%our_warehouse}}', 'company_id', '{{%companies}}', 'id', 'CASCADE', 'CASCADE');

        // сид: по одному центральному на компанию из существующих компаний
        $companies = (new \yii\db\Query())->select('id')->from('{{%companies}}')->where(['is_active'=>1])->column();
        foreach ($companies as $cid) {
            $exists = (new \yii\db\Query())->from('{{%our_warehouse}}')->where(['company_id'=>$cid])->exists();
            if (!$exists) {
                $this->insert('{{%our_warehouse}}', [
                    'company_id'=> $cid,
                    'name'=> 'Центральный',
                    'is_central'=> 1,
                    'is_fbs'=> 0,
                    'is_active'=> 1,
                ]);
            }
        }
        echo "  our_warehouse created\n";
    }

    public function safeDown()
    {
        $this->dropTable('{{%our_warehouse}}');
        echo "  rolled back\n";
    }
}
