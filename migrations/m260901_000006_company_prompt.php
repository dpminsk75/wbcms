<?php
use yii\db\Migration;

class m260901_000006_company_prompt extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'seo_prompt', $this->text()->null()->after('seo_openrouter_title')->comment('Системный промпт для SEO, null = дефолт'));
    }
    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'seo_prompt');
    }
}
