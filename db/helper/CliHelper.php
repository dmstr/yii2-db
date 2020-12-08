<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2020 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace dmstr\db\helper;


use mikehaertl\shellcommand\Command;
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


    public static function getMysqlCommand($mysqlExecutable = 'mysql', $db){
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
            'db'   => null,
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

        $optsMap = [
            \PDO::MYSQL_ATTR_SSL_CAPATH => '--ssl-capath=',
            \PDO::MYSQL_ATTR_SSL_CA => '--ssl-ca=',
            \PDO::MYSQL_ATTR_SSL_CERT => '--ssl-cert=',
            \PDO::MYSQL_ATTR_SSL_KEY => '--ssl-key=',
            \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => '--ssl-verify-server-cert=',
            \PDO::MYSQL_ATTR_SSL_CIPHER => '--ssl-cipher='
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