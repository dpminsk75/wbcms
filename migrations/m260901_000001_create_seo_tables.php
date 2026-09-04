<?php
use yii\db\Migration;

/**
 * SEO рекомендации WB: таблицы wb_seo_run и wb_seo_recommendation.
 */
class m260901_000001_create_seo_tables extends Migration
{
    public function safeUp()
    {
        // прогоны
        $this->createTable('{{%wb_seo_run}}', [
            'id' => $this->primaryKey(),
            'company_id' => $this->integer()->null()->comment('company, null=все'),
            'started_at' => $this->dateTime()->notNull(),
            'finished_at' => $this->dateTime()->null(),
            'date_from' => $this->date()->null()->comment('начало окна аналитики'),
            'date_to' => $this->date()->null()->comment('конец окна аналитики'),
            'model' => $this->string(100)->null()->comment('OpenRouter model'),
            'daily_limit' => $this->integer()->defaultValue(20),
            'days_window' => $this->integer()->defaultValue(30),
            'processed' => $this->integer()->defaultValue(0),
            'skipped' => $this->integer()->defaultValue(0),
            'errors' => $this->integer()->defaultValue(0),
            'status' => $this->string(20)->defaultValue('running')->comment('running/done/limit_reached/error'),
        ]);

        // рекомендации (история)
        $this->createTable('{{%wb_seo_recommendation}}', [
            'id' => $this->primaryKey(),
            'run_id' => $this->integer()->null(),
            'company_id' => $this->integer()->notNull(),
            'nmID' => $this->integer()->notNull()->comment('FK wbcards.nmID'),
            'old_title' => $this->string(500)->null(),
            'old_description' => $this->text()->null(),
            'new_title' => $this->string(500)->null(),
            'new_description' => $this->text()->null(),
            'rationale' => $this->text()->null()->comment('объяснение ИИ'),
            'keywords_added' => $this->json()->null(),
            'keywords_removed' => $this->json()->null(),
            'confidence' => $this->decimal(3,2)->null(),
            'model' => $this->string(100)->null(),
            'prompt_tokens' => $this->integer()->null(),
            'completion_tokens' => $this->integer()->null(),
            'raw_json' => $this->json()->null()->comment('сырой ответ + prompt'),
            'status' => $this->string(20)->notNull()->defaultValue('new')->comment('new/viewed'),
            'viewed_by' => $this->integer()->null(),
            'viewed_at' => $this->dateTime()->null(),
            'is_requeued' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'requeued_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_seo_rec_company_nm', '{{%wb_seo_recommendation}}', ['company_id', 'nmID']);
        $this->createIndex('idx_seo_rec_status', '{{%wb_seo_recommendation}}', ['status']);
        $this->createIndex('idx_seo_rec_created', '{{%wb_seo_recommendation}}', ['created_at']);
        $this->createIndex('idx_seo_rec_run', '{{%wb_seo_recommendation}}', ['run_id']);
        $this->createIndex('idx_seo_run_company', '{{%wb_seo_run}}', ['company_id']);

        $this->addForeignKey('fk_seo_rec_wbcards', '{{%wb_seo_recommendation}}', 'nmID', '{{%wbcards}}', 'nmID', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_seo_rec_run', '{{%wb_seo_recommendation}}', 'run_id', '{{%wb_seo_run}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_seo_rec_user', '{{%wb_seo_recommendation}}', 'viewed_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_seo_run_company', '{{%wb_seo_run}}', 'company_id', '{{%companies}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_seo_run_company', '{{%wb_seo_run}}');
        $this->dropForeignKey('fk_seo_rec_user', '{{%wb_seo_recommendation}}');
        $this->dropForeignKey('fk_seo_rec_run', '{{%wb_seo_recommendation}}');
        $this->dropForeignKey('fk_seo_rec_wbcards', '{{%wb_seo_recommendation}}');
        $this->dropTable('{{%wb_seo_recommendation}}');
        $this->dropTable('{{%wb_seo_run}}');
    }
}
