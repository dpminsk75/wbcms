<?php
use yii\db\Migration;

class m260901_000005_companies_openrouter extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'seo_openrouter_key', $this->text()->null()->after('seo_anti_spam_days')->comment('OpenRouter API key per company, null=из params'));
        $this->addColumn('{{%companies}}', 'seo_openrouter_referer', $this->string(255)->null()->after('seo_openrouter_key')->comment('HTTP-Referer'));
        $this->addColumn('{{%companies}}', 'seo_openrouter_title', $this->string(255)->null()->after('seo_openrouter_referer')->comment('X-Title'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'seo_openrouter_title');
        $this->dropColumn('{{%companies}}', 'seo_openrouter_referer');
        $this->dropColumn('{{%companies}}', 'seo_openrouter_key');
    }
}
