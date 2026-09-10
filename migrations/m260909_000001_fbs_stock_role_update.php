<?php
use yii\db\Migration;

/**
 * Догоняет m260828_000002 — добавляет маршруты для документов и реальных складов.
 * Старая миграция уже применена, поэтому новая.
 */
class m260909_000001_fbs_stock_role_update extends Migration
{
    public function safeUp()
    {
        $this->ensurePermission('manageFbsStocks', 'Управление остатками FBS (виртуал. склады)');
        $this->ensurePermission('/wb-fbs-virtual/*', 'Маршруты FBS virtual');
        $this->ensurePermission('/wb-fbs/*', 'Синхронизация складов WB');
        $this->ensurePermission('/wb-fbs-virtual/deduct-log', 'Лог вычета');
        $this->ensurePermission('/wb-doc/*', 'Документы склада');
        $this->ensurePermission('/our-warehouse/*', 'Реальные склады');

        $this->ensureRole('fbsManager', 'Управление остатками');

        $this->ensureChild('fbsManager', 'manageFbsStocks');
        $this->ensureChild('fbsManager', '/wb-fbs-virtual/*');
        $this->ensureChild('fbsManager', '/wb-fbs/*');
        $this->ensureChild('fbsManager', '/wb-fbs-virtual/deduct-log');
        $this->ensureChild('fbsManager', '/wb-doc/*');
        $this->ensureChild('fbsManager', '/our-warehouse/*');
        // viewDashboard уже был в 000002, но на всякий — не добавляем, чтобы не давать лишний доступ

        $this->ensureChild('admin', 'manageFbsStocks');

        echo "  fbsManager updated with /wb-doc/* and /our-warehouse/*\n";
    }

    public function safeDown()
    {
        $this->delete('{{%auth_item_child}}', ['parent'=>'fbsManager','child'=>['/wb-doc/*','/our-warehouse/*']]);
        $this->delete('{{%auth_item}}', ['name'=>['/wb-doc/*','/our-warehouse/*']]);
        echo "  rolled back update\n";
    }

    private function ensureRole($name,$desc){ $e=(new \yii\db\Query())->from('{{%auth_item}}')->where(['name'=>$name])->exists(); if(!$e) $this->insert('{{%auth_item}}',['name'=>$name,'type'=>1,'description'=>$desc,'created_at'=>time(),'updated_at'=>time()]); }
    private function ensurePermission($name,$desc){ $e=(new \yii\db\Query())->from('{{%auth_item}}')->where(['name'=>$name])->exists(); if(!$e) $this->insert('{{%auth_item}}',['name'=>$name,'type'=>2,'description'=>$desc,'created_at'=>time(),'updated_at'=>time()]); }
    private function ensureChild($p,$c){ $e=(new \yii\db\Query())->from('{{%auth_item_child}}')->where(['parent'=>$p,'child'=>$c])->exists(); if(!$e) $this->insert('{{%auth_item_child}}',['parent'=>$p,'child'=>$c]); }
}
