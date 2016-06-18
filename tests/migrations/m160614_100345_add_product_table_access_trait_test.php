<?php

use yii\db\Migration;

class m160614_100345_add_product_table_access_trait_test extends Migration
{
    public $tableName = 'product';

    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
        $this->createTable(
            $this->tableName,
            [
                'id'            => $this->primaryKey(),
                'title'         => $this->string(255)->notNull(),
                'access_domain' => $this->string(255),
                'access_owner'  => $this->integer(11),
                'access_read'   => $this->string(255),
                'access_update' => $this->string(255),
                'access_delete' => $this->string(255),
            ]
        );

        /**
         * add a test user
         */

        // Username: user
        // Password: user123
        $this->execute(
            "
        INSERT INTO `app_user` (`id`, `username`, `email`, `password_hash`, `auth_key`, `confirmed_at`, `unconfirmed_email`, `blocked_at`, `registration_ip`, `created_at`, `updated_at`, `flags`)
VALUES
	(2, 'user', 'dbtest@h17n.de', '$2y$10$.4aD9IK6OxJGL2R0OU75deMBl9HQTQ3b0OVdzHpzZtR/BfF8EVYEa', '49p1_5UDnzhlS0hI8mqP_k4dNfm8YFqw', 1465899608, NULL, NULL, '192.168.99.1', 1465899608, 1465899608, 0);
        "
        );

        /**
         * add test data
         */

        // access All
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => '*',
                'access_delete' => '*',
            ]
        );

        // access read Editor
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '3',
                'access_read'   => 'Editor',
                'access_update' => '*',
                'access_delete' => '*',
            ]
        );

        // access update Editor
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => 'Editor',
                'access_delete' => '*',
            ]
        );

        // access delete Editor
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => '*',
                'access_delete' => 'Editor',
            ]
        );

        // access owner user
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '2',
                'access_read'   => 'Supervisor',
                'access_update' => 'Supervisor',
                'access_delete' => 'Supervisor',
            ]
        );

        // access domain de
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'de',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => '*',
                'access_delete' => '*',
            ]
        );
        // access domain
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'en',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => '*',
                'access_delete' => '*',
            ]
        );
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'de',
                'access_owner'  => '3',
                'access_read'   => 'Editor',
                'access_update' => 'Supervisor',
                'access_delete' => 'Supervisor',
            ]
        );
        $this->insert(
            $this->tableName,
            [
                'title'         => 'Product',
                'access_domain' => 'fr',
                'access_owner'  => '3',
                'access_read'   => '*',
                'access_update' => 'Supervisor',
                'access_delete' => 'Supervisor',
            ]
        );
    }

    public function safeDown()
    {
        $this->dropTable($this->tableName);
        $this->delete('app_user', ['id' => 2]);
    }
}
