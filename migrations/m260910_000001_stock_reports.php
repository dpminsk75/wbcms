<?php
use yii\db\Migration;

class m260910_000001_stock_reports extends Migration
{
    public function safeUp()
    {
        $this->ensurePermission('/wb-doc-report/*', 'Отчеты по документам склада');
        $this->ensureChild('fbsManager', '/wb-doc-report/*');
        $this->ensureChild('admin', '/wb-doc-report/*');
        echo "  wb-doc-report perms added\n";
    }
    public function safeDown()
    {
        $this->delete('{{%auth_item_child}}', ['child'=>'/wb-doc-report/*']);
        $this->delete('{{%auth_item}}', ['name'=>'/wb-doc-report/*']);
    }
    private function ensurePermission($name,$desc){ $e=(new \yii\db\Query())->from('{{%auth_item}}')->where(['name'=>$name])->exists(); if(!$e) $this->insert('{{%auth_item}}',['name'=>$name,'type'=>2,'description'=>$desc,'created_at'=>time(),'updated_at'=>time()]); }
    private function ensureChild($p,$c){ $e=(new \yii\db\Query())->from('{{%auth_item_child}}')->where(['parent'=>$p,'child'=>$c])->exists(); if(!$e) $this->insert('{{%auth_item_child}}',['parent'=>$p,'child'=>$c]); }
}
