<?php
// file generated with AI assistance: Claude Code - 2026-10-22 12:05:00 UTC
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2020 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\helper;

use mikehaertl\shellcommand\Command;
use yii\base\Exception;
use yii\db\Connection;

/**
 * Class CliHelper
 *
 * This class provide helper to get params from PDO Connection as cli cmd args
 *
 * @package dmstr\db\helper
 * Author: Jens Giessmann <j.giessmann@herzogkommunikation.de>
 */
class CliHelper
{
    public static function getMysqlCommand($mysqlExecutable = 'mysql', ?Connection $db = null)
    {
        if ($db === null) {
            throw new Exception('Invalid database config');
        }

        $dsnOpts = CliHelper::getMysqlOptsFromDsn($db);

        $command = new Command($mysqlExecutable);
        $command->addArg('-h', $dsnOpts['host']);
        $command->addArg('-P', $dsnOpts['port']);
        $command->addArg('-u', $db->username);
        $command->addArg('--password=', $db->password);

        foreach (CliHelper::getMysqlCliArgsFromPdo($db) as $opt => $value) {
            $command->addArg($opt, $value);
        }

        return $command;
    }

    /**
     * parse dsn and return db host, db port and db name as array
     *
     * @param Connection $db
     *
     * @return array
     */
    public static function getMysqlOptsFromDsn(Connection $db)
    {

        $cliArgs = [
            'host' => null,
            'db' => null,
            'port' => 3306,
        ];

        preg_match('/host=([^;]*)/', $db->dsn, $hostMatches);
        $cliArgs['host'] = $hostMatches[1];
        preg_match('/dbname=([^;]*)/', $db->dsn, $databaseMatches);
        $cliArgs['db'] = $databaseMatches[1];
        preg_match('/port=([^;]*)/', $db->dsn, $portMatches);
        if (isset($portMatches[1])) {
            $cliArgs['port'] = $portMatches[1];
        }

        return $cliArgs;
    }

    /**
     * map PDO Attributes to mysql cli args
     *
     * @param Connection $db
     *
     * @return array
     */
    public static function getMysqlCliArgsFromPdo(Connection $db)
    {

        // PHP >= 8.4 provides Pdo\Mysql::ATTR_*, PDO::MYSQL_ATTR_* is deprecated since PHP 8.5
        $prefix = class_exists('Pdo\\Mysql') ? 'Pdo\\Mysql::ATTR_' : 'PDO::MYSQL_ATTR_';
        $optsMap = [
            constant($prefix . 'SSL_CAPATH') => '--ssl-capath=',
            constant($prefix . 'SSL_CA') => '--ssl-ca=',
            constant($prefix . 'SSL_CERT') => '--ssl-cert=',
            constant($prefix . 'SSL_KEY') => '--ssl-key=',
            constant($prefix . 'SSL_VERIFY_SERVER_CERT') => '--ssl-verify-server-cert=',
            constant($prefix . 'SSL_CIPHER') => '--ssl-cipher='
        ];

        $cliArgs = [];
        if (!empty($db->attributes)) {
            foreach ($db->attributes as $key => $value) {
                if (array_key_exists($key, $optsMap)) {
                    $cliArgs[$optsMap[$key]] = $value;
                }
            }
        }

        return $cliArgs;
    }

}
