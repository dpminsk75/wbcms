<?php

/**
 * Шаблон конфига БД.
 * Разворачивание: скопировать в config/db.php и подставить свои значения.
 * Реальный db.php не должен попадать в git (см. .gitignore).
 */

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yii2basic',
    'username' => 'root',
    'password' => 'ЗАМЕНИТЕ_НА_РЕАЛЬНЫЙ_ПАРОЛЬ',
    'charset' => 'utf8mb4',
    'attributes' => [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci",
    ],
];
