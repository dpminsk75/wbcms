<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $model_id
 * @property string|null $title
 * @property int $is_active
 * @property int $priority
 * @property int|null $context_length
 * @property int $success_count
 * @property int $error_count
 * @property int $consecutive_errors
 * @property string|null $last_error
 * @property string|null $last_429_at
 * @property string|null $last_success_at
 * @property string|null $cooldown_until
 * @property string $created_at
 * @property string $updated_at
 */
class WbSeoModel extends ActiveRecord
{
    public static function tableName() { return '{{%wb_seo_model}}'; }

    public function rules()
    {
        return [
            [['model_id','priority','created_at','updated_at'], 'required'],
            [['is_active','priority','context_length','success_count','error_count','consecutive_errors'], 'integer'],
            [['last_429_at','last_success_at','cooldown_until','created_at','updated_at'], 'safe'],
            [['model_id'], 'string', 'max'=>120],
            [['model_id'], 'unique'],
            [['title','last_error'], 'string', 'max'=>500],
        ];
    }

    public static function getActiveOrdered(): array
    {
        return self::find()
            ->where(['is_active'=>1])
            ->andWhere(['or', ['cooldown_until'=>null], ['<=','cooldown_until', date('Y-m-d H:i:s')]])
            ->orderBy(['priority'=>SORT_ASC, 'id'=>SORT_ASC])
            ->all();
    }

    public static function getAllActiveOrdered(): array
    {
        return self::find()->where(['is_active'=>1])->orderBy(['priority'=>SORT_ASC])->all();
    }

    public function markSuccess(): void
    {
        $this->success_count++;
        $this->consecutive_errors = 0;
        $this->last_success_at = date('Y-m-d H:i:s');
        $this->last_error = null;
        $this->cooldown_until = null;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markError(string $error, bool $is429 = false): void
    {
        $this->error_count++;
        $this->consecutive_errors++;
        $this->last_error = mb_substr($error,0,500);
        $this->updated_at = date('Y-m-d H:i:s');
        if ($is429) {
            $this->last_429_at = date('Y-m-d H:i:s');
            // экспоненциальный кулдаун: 5м, 15м, 60м, 180м
            $minutes = [5,15,60,180][$this->consecutive_errors - 1] ?? 180;
            if ($this->consecutive_errors >= 3) $minutes = 180;
            $this->cooldown_until = date('Y-m-d H:i:s', time() + $minutes*60);
        }
        // авто-отключение после 10 подряд ошибок
        if ($this->consecutive_errors >= 10) {
            $this->is_active = 0;
            $this->last_error .= ' | auto-disabled';
        }
        $this->save(false);
    }
}
