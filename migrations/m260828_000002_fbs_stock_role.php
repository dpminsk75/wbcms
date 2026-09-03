<?php
use yii\db\Migration;

/**
 * Роль "Управление остатками" — доступ только к wb-fbs-virtual.
 * Создает пермишен manageFbsStocks и маршруты /wb-fbs-virtual/*, /wb-fbs/*, роль fbsManager.
 */
class m260828_000002_fbs_stock_role extends Migration
{
    public function safeUp()
    {
        $this->ensurePermission('manageFbsStocks', 'Управление остатками FBS (виртуал. склады)');
        $this->ensurePermission('/wb-fbs-virtual/*', 'Маршруты FBS virtual');
        $this->ensurePermission('/wb-fbs/*', 'Синхронизация складов WB');
        $this->ensurePermission('/wb-fbs-virtual/deduct-log', 'Лог вычета');

        $this->ensureRole('fbsManager', 'Управление остатками');

        $this->ensureChild('fbsManager', 'manageFbsStocks');
        $this->ensureChild('fbsManager', '/wb-fbs-virtual/*');
        $this->ensureChild('fbsManager', '/wb-fbs/*');
        $this->ensureChild('fbsManager', '/wb-fbs-virtual/deduct-log');
        $this->ensureChild('fbsManager', 'viewDashboard');

        // Админ также может управлять остатками
        $this->ensureChild('admin', 'manageFbsStocks');

        echo "  fbsManager role created\n";
    }

    public function safeDown()
    {
        $this->delete('{{%auth_assignment}}', ['item_name' => 'fbsManager']);
        $this->delete('{{%auth_item_child}}', ['parent' => 'fbsManager']);
        $this->delete('{{%auth_item_child}}', ['child' => ['manageFbsStocks','/wb-fbs-virtual/*','/wb-fbs/*','/wb-fbs-virtual/deduct-log']]);
        $this->delete('{{%auth_item}}', ['name' => ['manageFbsStocks','/wb-fbs-virtual/*','/wb-fbs/*','/wb-fbs-virtual/deduct-log','fbsManager']]);
        echo "  fbsManager rolled back\n";
    }

    private function ensureRole($name, $desc)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item}}')->where(['name' => $name])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item}}', ['name'=>$name,'type'=>1,'description'=>$desc,'created_at'=>time(),'updated_at'=>time()]);
        }
    }
    private function ensurePermission($name, $desc)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item}}')->where(['name' => $name])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item}}', ['name'=>$name,'type'=>2,'description'=>$desc,'created_at'=>time(),'updated_at'=>time()]);
        }
    }
    private function ensureChild($parent, $child)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item_child}}')->where(['parent'=>$parent,'child'=>$child])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item_child}}', ['parent'=>$parent,'child'=>$child]);
        }
    }
}
