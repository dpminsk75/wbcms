<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\db\Query;

class CompanyManager extends Component
{
    const SESSION_KEY = 'current_company_id';
    const MODE_ALL = 'all';

    /** @var int|null */
    private $_currentId;

    /** @var bool */
    private $_initialized = false;

    /**
     * Получить ID текущей активной компании.
     * null — глобальный режим (все компании).
     */
    public function getCurrentId()
    {
        $this->ensureInitialized();
        return $this->_currentId;
    }

    /**
     * Установить ID текущей компании (при переключении в интерфейсе или в консольном цикле)
     */
    public function setCurrentId($id)
    {
        $this->_currentId = (int) $id;
        $this->_initialized = true;

        if (Yii::$app instanceof \yii\web\Application) {
            Yii::$app->session->set(self::SESSION_KEY, (int) $id);
        }
    }

    /**
     * Включить глобальный режим (все компании, без фильтра по company_id).
     */
    public function resetCurrentId()
    {
        $this->_currentId = null;
        $this->_initialized = true;

        if (Yii::$app instanceof \yii\web\Application) {
            Yii::$app->session->set(self::SESSION_KEY, self::MODE_ALL);
        }
    }

    public function isGlobalMode(): bool
    {
        if (Yii::$app instanceof \yii\web\Application && !Yii::$app->user->isGuest) {
            return Yii::$app->session->get(self::SESSION_KEY) === self::MODE_ALL;
        }

        return false;
    }

    public function hasSelection(): bool
    {
        if (Yii::$app instanceof \yii\web\Application && !Yii::$app->user->isGuest) {
            return Yii::$app->session->has(self::SESSION_KEY);
        }

        return false;
    }

    /**
     * Добавить фильтр company_id к yii\db\Query (для сырых запросов без AR).
     */
    public function applyToQuery(Query $query, string $tableAlias = ''): Query
    {
        $companyId = $this->getCurrentId();
        if ($companyId === null) {
            return $query;
        }

        $column = ($tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '') . 'company_id';
        return $query->andWhere([$column => $companyId]);
    }

    /**
     * SQL-фрагмент AND alias.company_id = :param для createCommand.
     */
    public function andSql(string $tableAlias = '', string $paramName = 'companyId'): string
    {
        if ($this->getCurrentId() === null) {
            return '';
        }

        $column = ($tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '') . 'company_id';
        $param = ':' . ltrim($paramName, ':');

        return " AND {$column} = {$param}";
    }

    /**
     * Параметры привязки для andSql() — merge с существующим массивом params.
     *
     * @return array<string, int>
     */
    public function sqlParams(string $paramName = 'companyId'): array
    {
        $companyId = $this->getCurrentId();
        if ($companyId === null) {
            return [];
        }

        return [ltrim($paramName, ':') => $companyId];
    }

    /**
     * @return array<int, array{id: int, name: string, api_key: string|null}>
     */
    public function getActiveCompanies(): array
    {
        return (new Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * Выполнить callback для каждой активной компании (консольные команды).
     */
    public function eachActiveCompany(callable $fn): void
    {
        foreach ($this->getActiveCompanies() as $company) {
            $this->setCurrentId($company['id']);
            $fn($company);
        }
    }

    private function ensureInitialized(): void
    {
        if ($this->_initialized) {
            return;
        }

        if (Yii::$app instanceof \yii\web\Application && !Yii::$app->user->isGuest) {
            if (Yii::$app->session->has(self::SESSION_KEY)) {
                $value = Yii::$app->session->get(self::SESSION_KEY);
                if ($value === self::MODE_ALL) {
                    $this->_currentId = null;
                } else {
                    $this->_currentId = (int) $value;
                }
            }
        }

        $this->_initialized = true;
    }
}
