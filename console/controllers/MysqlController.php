<?php

namespace dmstr\console\controllers;

use igorw\FailingTooHardException;
use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;

/**
 * MySQL database maintenance command.
 *
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2015 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class MysqlController extends Controller
{
    /**
     * @var int Number of retries for MySql create operations
     */
    public $mysqlRetryMaxCount = 20;

    /**
     * @var int Timeout in seconds between operations
     */
    public $mysqlRetryTimeout = 2;

    /**
     * @var array list of tables with only structural (schema) commands in dumps
     */
    public $noDataTables = [];

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        return array_merge(
            parent::options($actionId),
            ($actionId == 'dump') ? ['noDataTables'] : [] // action dump
        );
    }

    /**
     * Displays tables in database
     * @throws Exception
     */
    public function actionIndex()
    {
        $this->stdout("MySQL maintenance command\n");
        echo $cmd = 'mysqlshow -h ' . getenv('DB_PORT_3306_TCP_ADDR') .
            ' -u ' . getenv('DB_ENV_MYSQL_USER') .
            ' --password=' . getenv('DB_ENV_MYSQL_PASSWORD') . ' ' . getenv('DB_ENV_MYSQL_DATABASE');
        $this->stdout($this->execute($cmd));
        $this->stdout("\n");
    }

    /**
     * Create MySQL database from ENV vars and grant permissions
     *
     * Note: skips creation, if root password is not set
     *
     * @param $db database name
     */
    public function actionCreate($db = null)
    {
        if ($db === null) {
            $db = getenv("DATABASE_DSN_DB");
        }
        if (empty($db)) {
            $this->stdout('No database configured, skipping setup.');
            return;
        }

        $root = getenv("DB_ENV_MYSQL_ROOT_USER") ?: 'root';
        $root_password = getenv("DB_ENV_MYSQL_ROOT_PASSWORD");
        if (empty($root_password)) {
            return;
        }
        $user = getenv("DB_ENV_MYSQL_USER");
        $pass = getenv("DB_ENV_MYSQL_PASSWORD");
        $dsn = getenv("DATABASE_DSN_BASE");

        $this->stdout(
            "Checking database connection on DSN '{$dsn}' with user '{$root}'"
        );

        // trying to connect to database with PDO (20 times, interval 1 second)
        try {
            // retry an operation up to 20 times
            $pdo = \igorw\retry(
                $this->mysqlRetryMaxCount,
                function () use ($dsn, $root, $root_password) {
                    $this->stdout('.');
                    sleep($this->mysqlRetryTimeout);
                    return new \PDO($dsn, $root, $root_password);
                }
            );
        } catch (FailingTooHardException $e) {
            $this->stdout("\n\nError: Unable to connect to database '" . $e->getMessage() . "''");
            \Yii::$app->end(1);
        }
        $this->stdout(' [OK]');


        $this->stdout(
            "\nCreating database '{$db}' and granting permissions to user '{$user}' on DSN '{$dsn}' with user '{$root}'"
        );

        try {
            // retry an operation up to 20 times
            \igorw\retry(
                $this->mysqlRetryMaxCount,
                function () use ($dsn, $root, $root_password, $pdo, $user, $pass, $db) {
                    $pdo->exec(
                        "CREATE DATABASE IF NOT EXISTS `$db`;
                 GRANT ALL ON `$db`.* TO '$user'@'%' IDENTIFIED BY '$pass';
                 FLUSH PRIVILEGES;"
                    );
                    $this->stdout('.');
                    sleep($this->mysqlRetryTimeout);
                }
            );
        } catch (FailingTooHardException $e) {
            $this->stdout("\n\nError: Unable to setup database '" . $e->getMessage() . "''");
            \Yii::$app->end(1);
        }

        $this->stdout(' [OK]');
        $this->stdout("\n");
    }

    /**
     * Dumps current database tables to runtime folder
     *
     * @throws Exception
     */
    public function actionDump()
    {
        $this->stdout("MySQL dump command\n");
        $ignoreOpts = '';
        $noDataTables = '';
        foreach ($this->noDataTables as $table) {
            $ignoreOpts .= ' --ignore-table=' . getenv('DB_ENV_MYSQL_DATABASE') . '.' . $table;
            $noDataTables .= ' ' . $table;
        }
        $date = date('U');

        $dir = 'runtime/mysql';
        $file = $dir.'/full-' . $date . '.sql';

        $cmd = 'mkdir -p '.$dir.';';
        $this->execute($cmd);

        $cmd = 'mysqldump -h ' . getenv('DB_PORT_3306_TCP_ADDR') .
            ' -u ' . getenv('DB_ENV_MYSQL_USER') .
            ' --password=' . getenv('DB_ENV_MYSQL_PASSWORD') .
            ' ' . $ignoreOpts . ' ' . getenv('DB_ENV_MYSQL_DATABASE') . ' > ' . $file . ';';
        $this->execute($cmd);

        $cmd = 'mysqldump -h ' . getenv('DB_PORT_3306_TCP_ADDR') .
            ' -u ' . getenv('DB_ENV_MYSQL_USER') .
            ' --password=' . getenv('DB_ENV_MYSQL_PASSWORD') .
            ' --no-data ' . getenv(
                'DB_ENV_MYSQL_DATABASE'
            ) . ' ' . $noDataTables . ' >> ' . $file . ';';
        $this->execute($cmd);

        $this->stdout("Dump to file '$file' completed.\n");
    }

    private function execute($cmd)
    {
        $command = new Command();
        $command->setCommand($cmd);
        if ($command->execute()) {
            return $command->getOutput();
        } else {
            throw new Exception($command->getError());
        }
    }

}