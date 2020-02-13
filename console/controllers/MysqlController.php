<?php

namespace dmstr\console\controllers;

use igorw\FailingTooHardException;
use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

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
     * @var string default path/alias for file output
     */
    public $outputPath = '@runtime/mysql';

    private $_db;
    private $_dsn;
    private $_root;
    private $_rootPassword;
    private $_user;
    private $_pass;

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        switch ($actionId) {
            case $actionId == 'dump' || $actionId == 'x-dump-data':
                $additionalOptions = ['noDataTables'];
                break;
            case $actionId == 'x-dump' || 'export':
                $additionalOptions = ['includeTables', 'excludeTables', 'dataOnly', 'truncateTables', 'outputPath'];
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
        # if ENV is set get mysql Port, default 3306
        $port = 3306;
        if (getenv('DB_PORT_3306_TCP_PORT')) {
            $port = getenv('DB_PORT_3306_TCP_PORT');
        }

        $this->stdout("MySQL maintenance command\n");
        echo $cmd = 'mysqlshow -h ' . getenv('DB_PORT_3306_TCP_ADDR') .
            ' -u ' . getenv('DB_ENV_MYSQL_USER') . ' -P ' . $port .
            ' --password=' . getenv('DB_ENV_MYSQL_PASSWORD') . ' ' . getenv('DB_ENV_MYSQL_DATABASE');
        $this->stdout($this->execute($cmd));
        $this->stdout("\n");
    }


    public function actionWaitForConnection(
        $dsn = null,
        $user = null,
        $password = null
    )
    {
        $dsn = $dsn ?: getenv("DATABASE_DSN_BASE");
        $user = $user ?: getenv("DB_ENV_MYSQL_ROOT_USER");
        $password = $password ?: getenv("DB_ENV_MYSQL_ROOT_PASSWORD");

        if (empty($user) || empty($password) || empty($dsn)) {
            $this->stderr('Configuration failed, aborting.');
            return;
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
    public function actionCreate(
        $db = null,
        $dsn = null,
        $root = null,
        $rootPassword = null,
        $user = null,
        $pass = null
    )
    {
        $this->_db = $db;
        $this->_dsn = $dsn;
        $this->_root = $root;
        $this->_rootPassword = $rootPassword;
        $this->_user = $user;
        $this->_pass = $pass;

        $this->checkParameters();

        if (empty($this->_pass)) {
            $this->stderr('Configuration failed, aborting.');
            return;
        }

        // wait for database connection (BC)
        $this->actionWaitForConnection($dsn, $root, $rootPassword);

        // try to create a database for the user
        $this->stdout(
            "Creating database '{$this->_db}' and granting permissions to user '{$this->_user}' on DSN '{$this->_dsn}' with user '{$this->_root}'"
        );

        $pdo = new \PDO($this->_dsn, $this->_root, $this->_rootPassword);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$this->_db}`;
                 CREATE USER IF NOT EXISTS '{$this->_user}'@'%' IDENTIFIED BY '{$this->_pass}';
                 GRANT ALL ON `{$this->_db}`.* TO '{$this->_user}'@'%';
                 FLUSH PRIVILEGES;"
        );

        $this->stdout(' [OK]');
        $this->stdout("\n");
    }

    /**
     * Remove the current schema
     */
    public function actionDestroy($db = null,
                                  $dsn = null,
                                  $root = null,
                                  $rootPassword = null,
                                  $user = null,
                                  $pass = null)
    {
        if ($this->confirm('This is a destructive operation! Continue?', !$this->interactive)) {

            $this->_db = $db;
            $this->_dsn = $dsn;
            $this->_root = $root;
            $this->_rootPassword = $rootPassword;
            $this->_user = $user;

            $this->checkParameters();

            $pdo = new \PDO($this->_dsn, $this->_root, $this->_rootPassword);

            $this->stdout('Deleting database...' . PHP_EOL);
            $pdo->exec("DROP DATABASE `{$this->_db}`");
            $this->stdout('Deleting user...' . PHP_EOL);
            $pdo->exec("DROP USER '{$this->_user}'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        }
    }


    /**
     * export data tables, without logs and caches
     * @throws \yii\base\Exception
     * @since 0.8.0
     */
    public function actionExport()
    {
        $fileName = $this->getFilePrefix() . "_data.sql";
        $command = new Command('mysqldump');

        $command->addArg('-h', getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-P', getenv('DB_PORT_3306_TCP_PORT'));
        $command->addArg('-u', getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=', getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg('--no-create-info');
        $command->addArg('--skip-extended-insert');
        $command->addArg('--quick');
        $command->addArg('--no-autocommit');
        $command->addArg('--disable-keys');

        # if ENV is set get mysql Port
        if (getenv('DB_PORT_3306_TCP_PORT')) {
            $command->addArg('-P', getenv('DB_PORT_3306_TCP_PORT'));
        }

        $this->stdout("Ignoring tables: ");
        foreach ($this->noDataTables as $table) {
            $command->addArg('--ignore-table', getenv('DB_ENV_MYSQL_DATABASE') . '.' . $table);
            $this->stdout("$table, ");
        }
        $this->stdout("\n");
        $command->addArg(getenv('DB_ENV_MYSQL_DATABASE'));

        $command->execute();

        if ($command->getError()) {
            $this->stderr($command->getError() . "\n");
            \Yii::$app->end(1);
        }

        $dir = \Yii::getAlias($this->outputPath);
        FileHelper::createDirectory($dir);

        $dump = $command->getOutput();
        $dump = preg_replace('/LOCK TABLES (.+) WRITE;/', 'LOCK TABLES $1 WRITE; TRUNCATE TABLE $1;', $dump);
        $file = $dir . '/' . $fileName;

        file_put_contents($file, $dump);

        $this->stdout("\nMySQL dump successfully written to '$file'\n", Console::FG_GREEN);
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
        $file = $dir . '/full-' . $date . '.sql';

        $cmd = 'mkdir -p ' . $dir . ';';
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

        $this->stdout($cmd);
        $this->execute($cmd);

        $this->stdout("Dump to file '$file' completed.\n");
    }

    public function actionImport($file) {
        $command = new Command('mysql');
        $command->addArg('-h', getenv('DB_PORT_3306_TCP_ADDR'));
        $command->addArg('-P', getenv('DB_PORT_3306_TCP_PORT'));
        $command->addArg('-u', getenv('DB_ENV_MYSQL_USER'));
        $command->addArg('--password=', getenv('DB_ENV_MYSQL_PASSWORD'));
        $command->addArg('-D', getenv('DB_ENV_MYSQL_DATABASE'));
        $command->addArg('<', null, false);
        $command->addArg($file);

        $this->stdout('Running command:'.PHP_EOL);
        $this->stdout($command->getExecCommand());
        $this->stdout(PHP_EOL);

        if (!$command->execute()) {
            $this->stderr($command->getError());
            $this->stderr(PHP_EOL);
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

    private function checkParameters()
    {
        $this->_db = $this->_db ?: getenv("DATABASE_DSN_DB");
        $this->_dsn = $this->_dsn ?: getenv("DATABASE_DSN_BASE");
        $this->_root = $this->_root ?: getenv("DB_ENV_MYSQL_ROOT_USER");
        $this->_rootPassword = $this->_rootPassword ?: getenv("DB_ENV_MYSQL_ROOT_PASSWORD");
        $this->_user = $this->_user ?: getenv("DB_ENV_MYSQL_USER");
        $this->_pass = $this->_pass ?: getenv("DB_ENV_MYSQL_PASSWORD");

        // check dsn
        if (empty($this->_db)) {
            $this->stderr('No database configured, aborting.');
            return;
        }
        // check root user settings
        if (empty($this->_root)) {
            $this->stderr('No root user configured, aborting.');
            return;
        }
        if (empty($this->_rootPassword)) {
            $this->stderr('No root password configured, aborting.');
            return;
        }

        if (empty($this->_user) || empty($this->_dsn)) {
            $this->stderr('Configuration failed, aborting.');
            return;
        }
    }

}
