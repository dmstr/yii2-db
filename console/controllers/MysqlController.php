<?php

namespace dmstr\console\controllers;

use dmstr\db\helper\CliHelper;
use igorw\FailingTooHardException;
use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

/**
 * MySQL database maintenance command for current (db) connection
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
     * @var string Cache component
     */
    public $cache = 'cache';

    /**
     * @var string Database component
     */
    public $db = 'db';

    /**
     * @var bool Only print commands
     */
    public $dryRun = false;

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
     * @var $truncateTables bool [0|1] add truncate table command
     */
    public $truncateTables = true;

    /**
     * @var string default path/alias for file output
     */
    public $outputPath = '@runtime/mysql';

    /**
     * @var boolean show more messages
     */
    public $verbose = false;

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        switch (true) {
            case $actionId == 'dump':
                $additionalOptions = ['noDataTables', 'dryRun'];
                break;
            case $actionId == 'import';
                $additionalOptions = ['cache', 'dryRun'];
                break;
            case $actionId == 'export':
                $additionalOptions = ['includeTables', 'excludeTables', 'truncateTables', 'dryRun'];
                break;
            default:
                $additionalOptions = [];
        }

        return array_merge(
            parent::options($actionId),
            ['verbose', 'db', 'outputPath'], // global options
            $additionalOptions
        );
    }


    public function optionAliases()
    {
        return ArrayHelper::merge(
            parent::optionAliases(),
            [
                'v' => 'verbose',
                'n' => 'dryRun',
                'o' => 'outputPath',
                'I' => 'includeTables',
                'X' => 'excludeTables',
            ]
        );
    }

    public function beforeAction($action)
    {
        $db = \Yii::$app->get($this->db);
        $this->stdout($db->dsn . PHP_EOL, Console::FG_YELLOW);
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        $this->stdout(PHP_EOL);
        return parent::afterAction($action, $result);
    }

    /**
     * Displays tables in database
     * @throws Exception
     */
    public function actionIndex()
    {
        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);
        $cmd = CliHelper::getMysqlCommand('mysqlshow', $db);

        $cmd->addArg($opts['db']);

        $this->stdout($this->execute($cmd));
    }


    public function actionWaitForConnection($user = null, $password = null)
    {
        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);
        // use dsn without database
        $dsn = 'mysql:host=' . $opts['host'] . ';port=' . $opts['port'];

        if (!$user) {
            $user = $db->username;
        }
        if (!$password) {
            $password = $db->password;
        }

        // trying to connect to database with PDO (20 times, interval 1 second)
        $this->stdout(
            "Checking database connection on DSN '{$dsn}' with user '{$user}'"
        );

        try {
            // retry an operation up to 20 times
            $pdo = \igorw\retry(
                $this->mysqlRetryMaxCount,
                function () use ($dsn, $user, $password) {
                    $this->stdout('.');
                    sleep($this->mysqlRetryTimeout);
                    return new \PDO($dsn, $user, $password);
                }
            );
        } catch (FailingTooHardException $e) {
            $this->stderr("\n\nError: Unable to connect to database '" . $e->getMessage() . "''");
            \Yii::$app->end(1);
        }
        $this->stdout(' [OK]' . PHP_EOL);

    }

    /**
     * Create schema
     *
     * Note: Loads parameters from ENV vars, if empty.
     *
     * Creates database and grants permissions to user
     *
     * @param $root eg. ENV `DB_ENV_MYSQL_ROOT_USER
     * @param $rootPassword eg. ENV `DB_ENV_MYSQL_ROOT_USER`
     *
     * @throws \yii\base\ExitException
     */
    public function actionCreate(
        $root,
        $rootPassword
    ) {
        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);

        $dbName = $opts['db'];
        $dsn = 'mysql:host=' . $opts['host'] . ';port=' . $opts['port'];
        $user = $db->username;
        $password = $db->password;

        if (empty($password)) {
            $this->stderr('Configuration failed, aborting.');
            return;
        }

        $cmd = "CREATE DATABASE IF NOT EXISTS `{$dbName}`;
                 CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY '{$password}';
                 GRANT ALL ON `{$dbName}`.* TO '{$user}'@'%';
                 FLUSH PRIVILEGES;";
        if (!$this->dryRun) {
            // wait for database connection (BC)
            $this->actionWaitForConnection($root, $rootPassword);

            // try to create a database for the user
            $this->stdout(
                "Creating database from DSN for '{$user}' with user '{$root}'"
            );

            $pdo = new \PDO($dsn, $root, $rootPassword);
            $pdo->exec($cmd);

            $this->stdout(' [OK]');
            $this->stdout("\n");
        } else {
            $this->stdout($cmd);
        }
    }

    /**
     * Remove schema
     *
     * @param $root eg. ENV `DB_ENV_MYSQL_ROOT_USER
     * @param $rootPassword eg. ENV `DB_ENV_MYSQL_ROOT_USER`
     */
    public function actionDestroy(
        $root,
        $rootPassword
    ) {
        if ($this->confirm('This is a destructive operation! Continue?', !$this->interactive)) {
            $db = \Yii::$app->get($this->db);
            $opts = CliHelper::getMysqlOptsFromDsn($db);

            $dbName = $opts['db'];
            $dsn = $db->dsn;
            $user = $db->username;

            $pdo = new \PDO($dsn, $root, $rootPassword);

            $this->stdout('Deleting database...' . PHP_EOL);
            $pdo->exec("DROP DATABASE `{$dbName}`");
            $this->stdout("Deleting user '{$user}'..." . PHP_EOL);
            $pdo->exec("DROP USER '{$user}'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        }
    }


    /**
     * Export tables (INSERT only)
     * @throws \yii\base\Exception
     * @since 0.8.0
     */
    public function actionExport()
    {
        $latestMigrationId = $this->getLatestMigrationId();
        $date = date('ymd_His');
        $fileName = "{$latestMigrationId}x_export_at_{$date}.sql";

        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);

        $command = CliHelper::getMysqlCommand('mysqldump', $db);
        $command->addArg('--no-create-info');
        $command->addArg('--skip-extended-insert');
        $command->addArg('--quick');
        $command->addArg('--no-autocommit');
        $command->addArg('--disable-keys');

        // exclude-tables
        if ($this->excludeTables) {
            foreach ($this->excludeTables as $excludedTable) {
                $command->addArg('--ignore-table', $opts['db'] . '.' . $excludedTable);
            }
        }

        // database
        $command->addArg($opts['db']);

        // include tables
        if ($this->includeTables) {
            foreach ($this->includeTables as $includedTable) {
                $command->addArg($includedTable);
            }
        }

        if (!$this->dryRun) {
            $command->execute();
        } else {
            $this->stdout($command->getExecCommand());
            return;
        }

        if ($command->getError()) {
            $this->stderr($command->getError() . "\n");
            \Yii::$app->end(1);
        }

        $dir = \Yii::getAlias($this->outputPath);
        FileHelper::createDirectory($dir);

        $dump = $command->getOutput();
        if ($this->truncateTables) {
            $dump = preg_replace('/LOCK TABLES (.+) WRITE;/', 'LOCK TABLES $1 WRITE; TRUNCATE TABLE $1;', $dump);
        }

        $file = \Yii::getAlias($dir . '/' . $fileName);
        file_put_contents($file, $dump);

        $this->stdout("MySQL export successfully written to '$file'", Console::FG_GREEN);
    }


    /**
     * Dump schema (all tables)
     *
     * @throws Exception
     */
    public function actionDump()
    {
        // prepare vars
        $date = date('ymd_His');
        $dir = $this->outputPath;
        $appName = \Yii::$app->id;
        $file = \Yii::getAlias($dir . "/d{$date}_{$appName}.sql");
        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);

        FileHelper::createDirectory($dir);

        // dump tables with data
        $command = CliHelper::getMysqlCommand('mysqldump', $db);
        $command->addArg('--no-tablespaces');
        $command->addArg('--opt');

        if ($this->noDataTables) {
            $noDataTables = explode(',', $this->noDataTables);

            foreach ($noDataTables as $table) {
                $command->addArg('--ignore-table', $opts['db'] . '.' . $table);
            }
        }
        $command->addArg($opts['db']);
        $command->addArg(' > ', '', false);
        $command->addArg($file);
        if ($this->dryRun) {
            $this->stdout($command->getExecCommand() . PHP_EOL);
        } else {
            if ($this->verbose) {
                $this->stdout("Dumping tables with data...");
            }
            $this->execute($command);
        }


        if ($this->noDataTables) {
            // dump tables without data
            $commandNoData = CliHelper::getMysqlCommand('mysqldump', $db);
            $commandNoData->addArg('--no-tablespaces');
            $commandNoData->addArg('--no-data');
            $commandNoData->addArg('--opt');
            $commandNoData->addArg($opts['db']);

            foreach ($noDataTables as $table) {
                $commandNoData->addArg($table);
            }
            $commandNoData->addArg('>>', '', false);
            $commandNoData->addArg($file);
            if ($this->dryRun) {
                $this->stdout($commandNoData->getExecCommand());
            } else {
                if ($this->verbose) {
                    $this->stdout("no data tables...");
                }
                $this->execute($commandNoData);
            }
        }

        if ($this->verbose) {
            $this->stdout("done." . PHP_EOL);
        }
        $this->stdout($file);
    }

    /**
     * Import from file to database and flush cache
     * @param $file
     * @throws \yii\base\InvalidConfigException
     */
    public function actionImport($file)
    {
        $db = \Yii::$app->get($this->db);
        $opts = CliHelper::getMysqlOptsFromDsn($db);
        $command = CliHelper::getMysqlCommand('mysql', $db);
        $command->addArg('-D', $opts['db']);
        $command->addArg('<', null, false);
        $command->addArg($file);

        if ($this->dryRun) {
            $this->stdout($command->getExecCommand());
        } else {
            if ($this->confirm('This is a destructive operation! Continue?', !$this->interactive)) {
                if (!$command->execute()) {
                    $this->stderr($command->getError());
                    $this->stderr(PHP_EOL);
                }
            }

            \Yii::$app->cache->flush();
        }
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

    private function getFilePrefix()
    {
        $sanitizedVersion = defined('APP_VERSION') ?
            '_' . Inflector::slug(Inflector::camel2words(trim(APP_VERSION, '_')), '_') :
            '';
        return 'm' . gmdate('ymd_His') . '_' . \Yii::$app->id . $sanitizedVersion;
    }

    /**
     * @return mixed return the last applied migration id (m123456_123456)
     */
    private function getLatestMigrationId(){
        $command = new Command();
        $command->setCommand('yii migrate/history 1');
        $command->execute();
        $output = $command->getOutput();
        preg_match('/m[0-9_]{6}_[0-9_]{6}/', $output, $matches);
        $latestMigrationId = $matches[0];
        return $latestMigrationId;
    }
}
