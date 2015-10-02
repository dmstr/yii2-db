<?php

namespace dmstr\console\controllers;

use mikehaertl\shellcommand\Command;
use yii\console\Controller;
use yii\console\Exception;

/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2015 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class MysqlController extends Controller
{
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

    public function actionIndex()
    {
        $this->stdout("MySQL maintenance command\n");
        echo $cmd = 'mysqlshow -h ' . getenv('DB_PORT_3306_TCP_ADDR') .
            ' -u ' . getenv('DB_ENV_MYSQL_USER') .
            ' --password=' . getenv('DB_ENV_MYSQL_PASSWORD') . ' ' . getenv('DB_ENV_MYSQL_DATABASE');
        $this->stdout($this->execute($cmd));
        $this->stdout("\n");
    }

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