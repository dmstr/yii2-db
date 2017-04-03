<?php

namespace dmstr\console\controllers;

use igorw\FailingTooHardException;
use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\Console;
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
     * @var $truncateTables bool [0|1] add truncate table command
     */
    public $truncateTables = 0;

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
                $additionalOptions = ['includeTables', 'excludeTables', 'dataOnly', 'truncateTables'];
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
     * Create MySQL database
     *
     * Note: Loads parameters from ENV vars, if empty.
     *
     * Creates database and grants permissions to user
     *
     * @param null $db database name `DATABASE_DSN_DB`
     * @param null $dsn database base-DSN `DATABASE_DSN_BASE`
     * @param null $root `DB_ENV_MYSQL_ROOT_USER
     * @param null $rootPassword `DB_ENV_MYSQL_ROOT_USER`
     * @param null $user `DB_ENV_MYSQL_USER`
     * @param null $pass `DB_ENV_MYSQL_PASSWORD
     *
     * @throws \yii\base\ExitException
     */
    public function actionCreate($db = null,  $dsn = null, $root = null, $rootPassword = null, $user = null, $pass = null)
    {
        // check dsn
        if ($db === null) {
            $db = getenv("DATABASE_DSN_DB");
        }
        if (empty($db)) {
            $this->stdout('No database configured, aborting.');
            return;
        }

        // check root user settings
        $root = $root ?: getenv("DB_ENV_MYSQL_ROOT_USER");
        if (empty($root)) {
            $this->stdout('No root user configured, aborting.');
            return;
        }
        $rootPassword = $rootPassword ?: getenv("DB_ENV_MYSQL_ROOT_PASSWORD");
        if (empty($rootPassword)) {
            $this->stdout('No root password configured, aborting.');
            return;
        }

        $user = $user ?: getenv("DB_ENV_MYSQL_USER");
        $pass = $pass ?: getenv("DB_ENV_MYSQL_PASSWORD");
        $dsn = $dsn ?: getenv("DATABASE_DSN_BASE");

        if (empty($user) || empty($pass) || empty($dsn)) {
            $this->stdout('Configuration failed, aborting.');
            return;
        }

        // trying to connect to database with PDO (20 times, interval 1 second)
        $this->stdout(
            "Checking database connection on DSN '{$dsn}' with user '{$root}'"
        );

        try {
            // retry an operation up to 20 times
            $pdo = \igorw\retry(
                $this->mysqlRetryMaxCount,
                function () use ($dsn, $root, $rootPassword) {
                    $this->stdout('.');
                    sleep($this->mysqlRetryTimeout);
                    return new \PDO($dsn, $root, $rootPassword);
                }
            );
        } catch (FailingTooHardException $e) {
            $this->stdout("\n\nError: Unable to connect to database '" . $e->getMessage() . "''");
            \Yii::$app->end(1);
        }
        $this->stdout(' [OK]');


        // try to create a database for the user
        $this->stdout(
            "\nCreating database '{$db}' and granting permissions to user '{$user}' on DSN '{$dsn}' with user '{$root}'"
        );
        try {
            \igorw\retry(
                $this->mysqlRetryMaxCount,
                function () use ($dsn, $root, $rootPassword, $pdo, $user, $pass, $db) {
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
     * export data tables, without logs and caches
     * @throws \yii\base\Exception
     * @since 0.8.0
     */
    public function actionExport(){
        $fileName = $this->getFilePrefix()."_data.sql";
        $command = new Command('mysqldump');

        $command->addArg('-h',getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-u',getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=',getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg('--no-create-info');
        $command->addArg('--skip-extended-insert');

        $this->stdout("Ignoring tables: ");
        foreach ($this->noDataTables as $table) {
            $command->addArg('--ignore-table',getenv('DB_ENV_MYSQL_DATABASE') . '.' . $table);
            $this->stdout("$table, ");
        }
        $this->stdout("\n");
        $command->addArg(getenv('DB_ENV_MYSQL_DATABASE'));

        $command->execute();

        if ($command->getError()) {
            $this->stderr($command->getError()."\n");
            \Yii::$app->end(1);
        }

        $dir = \Yii::getAlias('@runtime/mysql');
        FileHelper::createDirectory($dir);

        $dump = $command->getOutput();
        $dump = preg_replace('/LOCK TABLES (.+) WRITE;/','LOCK TABLES $1 WRITE; TRUNCATE TABLE $1;',$dump);
        $file = $dir.'/'.$fileName;

        file_put_contents($file, $dump);

        $this->stdout("\nMySQL dump successfully written to '$file'\n", Console::FG_GREEN);
    }

    /**
     * Deprecated - alias for export
     */
    public function actionXDumpData(){
        \Yii::warning('x-dump-data is deprecated, please use export', __METHOD__);
        return $this->actionExport();
    }

    /**
     * EXPERIMENTAL: Schema and/or Data dumps
     *
     * @option: --includeTables
     * @option: --excludeTables
     * @option: --dataOnly [0|1]
     * @option: --truncateTables [0|1]
     */
    public function actionXDump()
    {
        $command        = new Command('mysqldump');
        $fileNameSuffix = 'schema-data';
        $truncateTable  = '';

        $command->addArg('-h', getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-u', getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=', getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg(getenv('DB_ENV_MYSQL_DATABASE'));

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

        // if truncate tables
        if ($this->truncateTables == 1) {
            $truncateTable = 'TRUNCATE TABLE $1;';
        }
        $dump = preg_replace('/LOCK TABLES (.+) WRITE;/', 'LOCK TABLES $1 WRITE; ' . $truncateTable, $dump);

        // generate file
        $dir = \Yii::getAlias('@runtime/mysql');
        FileHelper::createDirectory($dir);
        $fileName = $this->getFilePrefix() . '_' . $fileNameSuffix . '.sql';
        $file     = $dir . '/' . $fileName;
        file_put_contents($file, $dump);

        $this->stdout("\nMySQL dump successfully written to '$file'\n", Console::FG_GREEN);
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

    private function getFilePrefix(){
        return 'm'.gmdate('ymd_His').'_'.\Yii::$app->id.(defined('APP_VERSION')?'_'.trim(APP_VERSION):'');
    }

}