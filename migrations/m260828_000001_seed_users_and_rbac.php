<?php
use yii\db\Migration;

/**
 * Сид 3-х пользователей из UserFile + базовые RBAC роли.
 * Безопасно для повторного прогона (INSERT IGNORE / exists-check).
 * После прогона проверить логин каждым юзером, затем удалить UserFile.php.
 */
class m260828_000001_seed_users_and_rbac extends Migration
{
    private $users = [
        // id, username, email, plainPassword
        [100, 'admin',  'admin@example.com',  'admin'],
        [101, 'toloka', 'toloka@example.com', '2026'],
        [102, 'tamila', 'tamila@example.com', 'user'],
    ];

    public function safeUp()
    {
        $now = time();
        $security = Yii::$app->security;
        $db = $this->db;

        // 1) Пользователи + профили (Da\User структура)
        foreach ($this->users as [$id, $username, $email, $plain]) {
            $exists = (new \yii\db\Query())->from('{{%user}}')->where(['id' => $id])->exists();
            if (!$exists) {
                $existsByName = (new \yii\db\Query())->from('{{%user}}')->where(['username' => $username])->exists();
                if ($existsByName) {
                    echo "  skip user $username — already exists by username\n";
                } else {
                    $this->insert('{{%user}}', [
                        'id' => $id,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => $security->generatePasswordHash($plain),
                        'auth_key' => $security->generateRandomString(),
                        'confirmed_at' => $now,
                        'blocked_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'flags' => 0,
                    ]);
                    echo "  inserted user $username id=$id\n";
                }
            } else {
                echo "  skip user $username id=$id — exists\n";
            }

            // profile (PK = user_id)
            $pExists = (new \yii\db\Query())->from('{{%profile}}')->where(['user_id' => $id])->exists();
            if (!$pExists) {
                $this->insert('{{%profile}}', [
                    'user_id' => $id,
                    'name' => $username,
                    'public_email' => null,
                    'gravatar_email' => $email,
                    'timezone' => 'Europe/Minsk',
                ]);
            }
        }

        // Сдвинуть AUTO_INCREMENT чтобы не пересечься с ручными id 100+
        $maxId = (new \yii\db\Query())->from('{{%user}}')->max('id');
        if ($maxId) {
            $db->createCommand("ALTER TABLE {{%user}} AUTO_INCREMENT=" . ($maxId + 1))->execute();
        }

        // 2) RBAC роли (DbManager таблицы уже есть из structure.sql)
        $this->ensureRole('admin', 'Полный доступ');
        $this->ensureRole('manager', 'Менеджер — видит отчеты/данные');
        $this->ensureRole('viewer', 'Только просмотр дашборда');

        // Иерархия: admin -> manager -> viewer
        $this->ensureChild('admin', 'manager');
        $this->ensureChild('manager', 'viewer');

        // Пермишены-заглушки (потом наполнишь в /admin)
        $this->ensurePermission('viewDashboard', 'Видеть дашборд');
        $this->ensurePermission('viewReports', 'Видеть отчеты');
        $this->ensurePermission('viewOrders', 'Видеть заказы/продажи');
        $this->ensurePermission('manageCompanies', 'Редактировать компании');
        $this->ensurePermission('manageUsers', 'Управлять пользователями');

        $this->ensureChild('viewer', 'viewDashboard');
        $this->ensureChild('manager', 'viewReports');
        $this->ensureChild('manager', 'viewOrders');
        $this->ensureChild('admin', 'manageCompanies');
        $this->ensureChild('admin', 'manageUsers');

        // Назначения: admin->100, остальные пока viewer (поменяешь в /admin)
        $this->ensureAssignment('admin', 100);
        $this->ensureAssignment('viewer', 101);
        $this->ensureAssignment('viewer', 102);
    }

    public function safeDown()
    {
        // Откатывает только назначения/роли этого сида, юзеров не трогает
        $this->delete('{{%auth_assignment}}', ['item_name' => ['admin','manager','viewer']]);
        $this->delete('{{%auth_item_child}}', ['parent' => ['admin','manager','viewer']]);
        $this->delete('{{%auth_item_child}}', ['child' => ['viewDashboard','viewReports','viewOrders','manageCompanies','manageUsers']]);
        $this->delete('{{%auth_item}}', ['name' => ['viewDashboard','viewReports','viewOrders','manageCompanies','manageUsers','viewer','manager','admin']]);
        echo "  rolled back RBAC, users kept\n";
    }

    private function ensureRole($name, $desc)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item}}')->where(['name' => $name])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item}}', [
                'name' => $name, 'type' => 1, 'description' => $desc,
                'created_at' => time(), 'updated_at' => time(),
            ]);
        }
    }

    private function ensurePermission($name, $desc)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item}}')->where(['name' => $name])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item}}', [
                'name' => $name, 'type' => 2, 'description' => $desc,
                'created_at' => time(), 'updated_at' => time(),
            ]);
        }
    }

    private function ensureChild($parent, $child)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_item_child}}')->where(['parent' => $parent, 'child' => $child])->exists();
        if (!$exists) {
            $this->insert('{{%auth_item_child}}', ['parent' => $parent, 'child' => $child]);
        }
    }

    private function ensureAssignment($role, $userId)
    {
        $exists = (new \yii\db\Query())->from('{{%auth_assignment}}')->where(['item_name' => $role, 'user_id' => (string)$userId])->exists();
        if (!$exists) {
            $this->insert('{{%auth_assignment}}', [
                'item_name' => $role, 'user_id' => (string)$userId, 'created_at' => time(),
            ]);
        }
    }
}
