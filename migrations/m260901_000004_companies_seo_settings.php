<?php
use yii\db\Migration;

class m260901_000004_companies_seo_settings extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'seo_model', $this->string(120)->null()->after('api_key')->comment('OpenRouter model, null = из params'));
        $this->addColumn('{{%companies}}', 'seo_daily_limit', $this->integer()->null()->after('seo_model')->comment('дневной лимит генераций, null = из params'));
        $this->addColumn('{{%companies}}', 'seo_desc_min', $this->integer()->null()->after('seo_daily_limit')->comment('мин длина описания'));
        $this->addColumn('{{%companies}}', 'seo_desc_max', $this->integer()->null()->after('seo_desc_min')->comment('макс длина описания'));
        $this->addColumn('{{%companies}}', 'seo_anti_spam_days', $this->integer()->null()->after('seo_desc_max')->comment('анти-спам дней'));

        // дефолты из текущих params (800/1200/14) оставляем null = фолбэк, но можно проставить
        // $this->update('{{%companies}}', ['seo_desc_min'=>800,'seo_desc_max'=>1200,'seo_anti_spam_days'=>14]);
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'seo_anti_spam_days');
        $this->dropColumn('{{%companies}}', 'seo_desc_max');
        $this->dropColumn('{{%companies}}', 'seo_desc_min');
        $this->dropColumn('{{%companies}}', 'seo_daily_limit');
        $this->dropColumn('{{%companies}}', 'seo_model');
    }
}
