<?php
use yii\db\Migration;

/**
 * RBAC для SEO рекомендаций.
 * Пермишен viewSeo -> manager/viewer, роль seoManager опционально.
 */
class m260901_000002_seo_rbac extends Migration
{
    public function safeUp()
    {
        $this->ensurePermission('viewSeo', 'Просмотр SEO рекомендаций');
        $this->ensurePermission('/seo/*', 'Маршруты SEO');

        $this->ensureRole('seoManager', 'SEO менеджер');

        $this->ensureChild('seoManager', 'viewSeo');
        $this->ensureChild('seoManager', '/seo/*');
        $this->ensureChild('seoManager', 'viewDashboard');

        // manager и admin тоже видят SEO
        $this->ensureChild('manager', 'viewSeo');
        $this->ensureChild('manager', '/seo/*');
        $this->ensureChild('admin', 'viewSeo');
        $this->ensureChild('admin', '/seo/*');

        echo "  viewSeo permissions created\n";
    }

    public function safeDown()
    {
        $this->delete('{{%auth_assignment}}', ['item_name' => 'seoManager']);
        $this->delete('{{%auth_item_child}}', ['parent' => 'seoManager']);
        $this->delete('{{%auth_item_child}}', ['child' => ['viewSeo','/seo/*']]);
        $this->delete('{{%auth_item}}', ['name' => ['viewSeo','/seo/*','seoManager']]);
        echo "  seo rbac rolled back\n";
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
