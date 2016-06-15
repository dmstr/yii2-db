<?php

use yii\db\Migration;

class m160609_090908_add_access_columns extends Migration
{
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
        // define all table names you want to equip with access control
        $tableNames = ['{%table}', '{%table}'];

        // add the access control columns to the defined tables
        foreach ($tableNames as $tableName) {
            $this->addColumn($tableName, 'access_owner', 'INT(11) NULL');
            $this->addColumn($tableName, 'access_domain', 'VARCHAR(255) NULL');
            $this->addColumn($tableName, 'access_read', 'VARCHAR(255) NULL');
            $this->addColumn($tableName, 'access_update', 'VARCHAR(255) NULL');
            $this->addColumn($tableName, 'access_delete', 'VARCHAR(255) NULL');
        }
    }

    public function safeDown()
    {
        echo "m160609_090908_add_access_columns cannot be reverted.\n";

        return false;
    }
}
