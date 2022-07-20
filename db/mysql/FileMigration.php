<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2014 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\mysql;

use dmstr\db\helper\CliHelper;
use mikehaertl\shellcommand\Command;
use yii\base\Exception;
use yii\db\Migration;


/**
 * Class FileMigration
 * @package common\components
 * @author Tobias Munk <tobias@diemeisterei.de>
 */
class FileMigration extends Migration
{

    public $file = null;
    public $mysqlExecutable = 'mysql';
    public $mysqlOptions = [];

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

        $dsnOpts = CliHelper::getMysqlOptsFromDsn($this->db);

        $command = new Command($this->mysqlExecutable);
        $command->addArg('-h', $dsnOpts['host']);
        $command->addArg('-P', $dsnOpts['port']);
        $command->addArg('-u', $this->db->username);
        $command->addArg('--password=', $this->db->password);
        if ($this->db->charset) {
            $command->addArg('--default-character-set=', $this->db->charset);
        }

        foreach (CliHelper::getMysqlCliArgsFromPdo($this->db) as $opt => $value) {
                $command->addArg($opt, $value);
        }

        $cmd      = $command->getExecCommand()." \"{$dsnOpts['db']}\" < \"{$this->file}\"";
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
