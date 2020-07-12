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
 * Class FileOverConnectionMigration
 * @package common\components
 * @author Dmitry Derepko <xepozz@list.ru>
 */
class FileOverConnectionMigration extends Migration
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
        $sql = file_get_contents($this->file);

        $this->db->createCommand($sql)->execute();
    }

    public function down()
    {
        echo get_class($this) . " cannot be reverted.\n";
        return false;
    }
}
