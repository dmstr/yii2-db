<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2014 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\mysql;

use mikehaertl\shellcommand\Command;
use yii\base\Exception;
use yii\db\Migration;


/**
 * Class MysqlFileMigration
 * @package common\components
 * @author Tobias Munk <tobias@diemeisterei.de>
 */
class FileMigration extends Migration
{

    public $file = null;
    public $mysqlExecutable = 'mysql';

    public function init()
    {
        parent::init();

        if ($this->file === null) {
            $reflection = new \ReflectionClass($this);
            $this->file = str_replace('.php', '.sql', $reflection->getFileName());
        } else {
            $reflection = new \ReflectionClass($this);
            $this->file = dirname($reflection->getFileName()).DIRECTORY_SEPARATOR.$this->file;
        }

        if (!is_file($this->file)) {
            throw new Exception("File {$this->file} not found");
        }
    }

    public function up()
    {
        preg_match('/host=([^;]*)/', $this->db->dsn, $hostMatches);
        $hostName = $hostMatches[1];
        preg_match('/dbname=([^;]*)/', $this->db->dsn, $databaseMatches);
        $databaseName = $databaseMatches[1];
        preg_match('/port=([^;]*)/', $this->db->dsn, $portMatches);
        if (isset($portMatches[1])) {
            $port = $portMatches[1];
        } else {
            $port = "3306";
        }

        $command = new Command($this->mysqlExecutable);
        $command->addArg('-h', $hostName);
        $command->addArg('-P', $port);
        $command->addArg('-u', $this->db->username);
        $command->addArg('--password=', $this->db->password);

        $cmd      = $command->getExecCommand()." \"{$databaseName}\" < \"{$this->file}\"";
        #echo "    ".$cmd . "\n"; // TODO echo only with --verbose
        exec($cmd, $output, $return);

        if ($return !== 0) {
            //var_dump($output, $return);
            return false;
        } else {
            return true;
        }
    }

    public function down()
    {
        echo $this::className() . " cannot be reverted.\n";
        return false;
    }

} 
