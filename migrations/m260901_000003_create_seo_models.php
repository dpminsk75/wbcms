<?php
use yii\db\Migration;

/**
 * Таблица ротации моделей OpenRouter для SEO.
 */
class m260901_000003_create_seo_models extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%wb_seo_model}}', [
            'id' => $this->primaryKey(),
            'model_id' => $this->string(120)->notNull()->comment('slug OpenRouter, напр google/gemma-4-31b-it:free'),
            'title' => $this->string(255)->null()->comment('человеческое имя'),
            'is_active' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'priority' => $this->integer()->notNull()->defaultValue(100)->comment('меньше = выше приоритет'),
            'context_length' => $this->integer()->null(),
            'success_count' => $this->integer()->notNull()->defaultValue(0),
            'error_count' => $this->integer()->notNull()->defaultValue(0),
            'consecutive_errors' => $this->integer()->notNull()->defaultValue(0),
            'last_error' => $this->string(500)->null(),
            'last_429_at' => $this->dateTime()->null(),
            'last_success_at' => $this->dateTime()->null(),
            'cooldown_until' => $this->dateTime()->null()->comment('не трогать до этого времени после 429'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('uq_seo_model_id', '{{%wb_seo_model}}', 'model_id', true);
        $this->createIndex('idx_seo_model_active_prio', '{{%wb_seo_model}}', ['is_active', 'priority']);

        // сид из твоего списка free
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['google/gemma-4-31b-it:free', 'Google: Gemma 4 31B', 10, 262144],
            ['google/gemma-4-26b-a4b-it:free', 'Google: Gemma 4 26B A4B', 11, 262144],
            ['z-ai/glm-5.2:free', 'Z.ai: GLM 5.2', 20, 256000],
            ['nvidia/nemotron-3.5-lightning:free', 'NVIDIA: Nemotron 3.5 Lightning', 30, 1000000],
            ['nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free', 'NVIDIA: Nemotron 3 Nano Reasoning', 31, 256000],
            ['minimax/minimax-m3:free', 'MiniMax: M3', 40, 1048576],
            ['minimax/minimax-m2.7:free', 'MiniMax: M2.7', 41, 196608],
            ['inclusionai/ling-3.0-flash-fin:free', 'Ling 3.0 Flash Fin', 50, 262144],
            ['liquid/lfm-2.5-2.6b:free', 'LiquidAI: LFM2.5-2.6B', 60, 65536],
            ['thinkingmachines/inkling-small:free', 'Inkling Small', 70, 1048576],
            ['poolside/laguna-s-2.1:free', 'Laguna S 2.1', 80, 262144],
        ];
        foreach ($rows as [$mid, $title, $prio, $ctx]) {
            $this->insert('{{%wb_seo_model}}', [
                'model_id' => $mid, 'title' => $title, 'is_active' => 1, 'priority' => $prio,
                'context_length' => $ctx, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%wb_seo_model}}');
    }
}
