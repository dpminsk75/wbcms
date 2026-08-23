<?php

namespace app\models;

use app\models\scopes\CompanyActiveQuery;

trait CompanyScopedTrait
{
    public static function find()
    {
        return new CompanyActiveQuery(static::class);
    }
}
