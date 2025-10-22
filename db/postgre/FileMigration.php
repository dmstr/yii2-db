<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2025 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\postgre;

use Exception;
use yii\db\Migration;

/**
 * Class FileMigration
 * @package common\components
 * Elias Luhr <e.luhr@herzogkommunikation.de>
 */
class FileMigration extends Migration
{
    public $file = null;

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

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // Open connection if not opened already as we execute raw SQL on PDO we have no "autoconnect magick" here.
        if (!$this->db->isActive) {
            $this->db->open();
        }

        $sql = file_get_contents($this->file);

        try {
            $result = $this->db->pdo->exec($sql);
            echo "Success exec: " . $this->file . "\n";
        } catch (Exception $exception) {
            echo "\n-----\n" . $exception->getMessage() . "\n-----\n";
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        echo $this::class . " cannot be reverted.\n";
        return false;
    }
}
