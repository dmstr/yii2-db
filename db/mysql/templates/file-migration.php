<?php
/**
 * This view is used by console/controllers/MigrateController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */

echo "<?php\n";
?>

use dmstr\db\mysql\FileMigration;

class <?= $className ?> extends FileMigration
{
    # create a sql file `<?= $className ?>.sql` or adjust and uncomment the following line, do not change this class name
    //public $file = 'custom-filename.sql';
}
