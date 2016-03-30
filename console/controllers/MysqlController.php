<?php

namespace dmstr\console\controllers;

use igorw\FailingTooHardException;
use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\FileHelper;

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
     * @var array list of tables to include in dumps
     */
    public $includeTables = [];

    /**
     * @var array list of tables to exclude in dumps
     */
    public $excludeTables = [];

    /**
     * @var $dataOnly bool [0|1] dump only data
     */
    public $dataOnly = 0;

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        switch($actionId) {
            case $actionId == 'dump' || $actionId == 'x-dump-data':
                $additionalOptions = ['noDataTables'];
                break;
            case $actionId == 'x-dump':
                $additionalOptions = ['includeTables', 'excludeTables', 'dataOnly'];
                break;
            default:
                $additionalOptions = [];
        }
        return array_merge(
            parent::options($actionId),
            $additionalOptions
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

    /**
     * EXPERIMENTAL: data only dump
     * @throws \yii\base\Exception
     */
    public function actionXDumpData(){
        $command = new Command('mysqldump');

        $command->addArg('-h',getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-u',getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=',getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg('--no-create-info');
        foreach ($this->noDataTables as $table) {
            $command->addArg('--ignore-table',getenv('DB_ENV_MYSQL_DATABASE') . '.' . $table);
        }
        $command->addArg(getenv('DB_ENV_MYSQL_DATABASE'));

        $command->execute();

        $dir = \Yii::getAlias('@runtime/mysql');
        FileHelper::createDirectory($dir);
        $fileName= 'data.sql';

        $dump = $command->getOutput();
        $dump = preg_replace('/LOCK TABLES (.+) WRITE;/','LOCK TABLES $1 WRITE; TRUNCATE TABLE $1;',$dump);

        file_put_contents($dir.'/'.$fileName, $dump);
        $this->stdout('Done.');
    }

    /**
     * EXPERIMENTAL: Schema and/or Data dumps
     *
     * @option: --includeTables
     * @option: --excludeTables
     * @option: --dataOnly [0|1]
     */
    public function actionXDump()
    {
        $date    = date('U');
        $command = new Command('mysqldump');

        $command->addArg('-h', getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-u', getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=', getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg(getenv('DB_ENV_MYSQL_DATABASE'));

        // default file name suffix
        $fileNameSuffix = 'schema-data';

        // if only data
        if ($this->dataOnly == 1) {
            $fileNameSuffix = 'data';
            $command->addArg('--no-create-info');
        }

        // if include tables
        if (!empty($this->includeTables)) {
            foreach ($this->includeTables as $table) {
                $command->addArg($table);
            }
        }

        // if exclude tables
        if (!empty($this->excludeTables)) {
            foreach ($this->excludeTables as $table) {
                $command->addArg('--ignore-table', getenv('DB_ENV_MYSQL_DATABASE') . '.' . $table);
            }
        }

        $command->execute();

        $dump = $command->getOutput();
        $dump = preg_replace('/LOCK TABLES (.+) WRITE;/', 'LOCK TABLES $1 WRITE; TRUNCATE TABLE $1;', $dump);

        // generate file
        $dir = \Yii::getAlias('@runtime/mysql');
        FileHelper::createDirectory($dir);
        $fileName = $date . '_' . getenv('DB_ENV_MYSQL_DATABASE') . '_' . $fileNameSuffix . '.sql';
        $file     = $dir . '/' . $fileName;
        file_put_contents($file, $dump);

        $this->stdout("\n");
        $this->stdout('MYSQL Dump successfully saved to');
        $this->stdout("\n" . $file);
        $this->stdout("\n\n");
    }

    /**
     * @param $cmd
     *
     * @return mixed
     * @throws Exception
     */
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