<?php
use yii\db\Migration;

class m260907_000001_add_summary_model_to_companies extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%companies}}', 'seo_summary_model', $this->string(120)->null()->after('seo_model')->comment('Модель для сводки конкурентов (платная)'));
        $this->addColumn('{{%companies}}', 'seo_summary_max_tokens', $this->integer()->null()->after('seo_summary_model')->comment('max_tokens для сводки'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%companies}}', 'seo_summary_max_tokens');
        $this->dropColumn('{{%companies}}', 'seo_summary_model');
    }
}
