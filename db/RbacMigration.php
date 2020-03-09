<?php

/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2019 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db;


use Yii;
use yii\base\ErrorException;
use yii\db\Exception;
use yii\db\Migration;
use yii\rbac\Item;
use yii\rbac\ManagerInterface;
use yii\rbac\Permission;
use yii\rbac\Role;

/**
 * Just extend your migration class from this one. => mxxxxxx_xxxxxx_migration_namee extends project\components\RbacMigration
 * Generates roles and permissions recursively when defined in following pattern:
 *
 * use yii\rbac\Item;
 *
 * public $privileges = [
 *      [
 *          '_exists' => true,
 *          'name' => 'Role_0',
 *          'type' => Item::TYPE_ROLE,
 *          'children' => [
 *              [
 *                  'name' => 'permission_0',
 *                  'type' => Item::TYPE_PERMISSION
 *              ],
 *              [
 *                  '_force' => true,
 *                  'name' => 'permission_1',
 *                  'type' => Item::TYPE_PERMISSION,
 *                  'rule' => [
 *                      'name' => 'Rule0',
 *                      'class' => some\namespaced\Rule::class
 *                  ]
 *              ],
 *              [
 *                  'name' => 'Role_1',
 *                  'type' => Item::TYPE_PERMISSION,
 *                  'children' => [
 *                      [
 *                          'name' => 'permission_2',
 *                          'type' => Item::TYPE_PERMISSION
 *                      ]
 *                  ]
 *              ]
 *          ]
 *      ],
 *      [
 *          'name' => 'Role_2',
 *          'type' => Item::TYPE_ROLE
 *      ]
 * ];
 *
 *
 *
 * @package project\components
 * @author Elias Luhr <e.luhr@herzogkommunikation.de>
 *
 * @property array $privileges
 * @property ManagerInterface $authManager
 */
class RbacMigration extends Migration
{
    public $privileges = [];
    public $authManager;

    public function init()
    {
        $this->authManager = $this->authManager ?? Yii::$app->authManager;
        parent::init();
    }

    /**
     * @throws \yii\base\Exception
     * @throws ErrorException
     */
    public function safeUp()
    {
        $this->generatePrivileges($this->privileges);
    }


    /**
     * @return false
     */
    public function safeDown()
    {
        $this->removePrivileges($this->privileges);
    }

    /**
     * Generate privileges recursively
     *
     * @param array $privileges
     * @throws \yii\base\Exception
     * @throws ErrorException
     */
    private function generatePrivileges($privileges = [], $parent = null)
    {
        foreach ($privileges as $privilege) {
            #var_dump($privilege['_exists']);
            if (!isset($privilege['_exists'])) {
                $current = $this->createPrivilege(
                    $privilege['name'],
                    $privilege['type'],
                    $privilege['description'] ?? null,
                    $privilege['rule'] ?? [],
                    $privilege['_force'] ?? null
                );
            } else {
                echo "exists";
                $current = Yii::$app->authManager->getRole($privilege['name']);
                if (!$current) {
                    throw new \yii\base\Exception("Item '{$privilege['name']}' not found");
                }
            }

            if ($parent) {
                if ($this->authManager->hasChild($parent, $current)) {
                    echo "Existing child '" . $current->name . "' to '" . $parent->name . "'" . PHP_EOL;
                } else if (!$this->authManager->addChild($parent, $current)) {
                    throw new ErrorException('Cannot add ' . $current['name'] . ' to ' . $parent['name']);
                } else {
                    echo "Added child '" . $current->name . "' to '" . $parent->name . "'" . PHP_EOL;
                }
            }

            $this->generatePrivileges($privilege['children'] ?? [], $current);
        }
    }

    /**
     * Create privilege if not exist and returns its object
     *
     * @param string $name
     * @param string $type
     * @param array $rule_data
     * @return Permission|Role
     * @throws ErrorException
     */
    private function createPrivilege($name, $type, $description, $rule_data = [], $force = false)
    {
        $type_name = ($type === Item::TYPE_ROLE ? 'Role' : 'Permission');

        $getter = 'get' . $type_name;

        // check if permission or role exists and create it
        if ($force || $this->authManager->{$getter}($name) === null) {
            echo "Creating $type_name: $name" . PHP_EOL;
            $privilege = $this->authManager->{'create' . $type_name}($name);
            $privilege->description = $description;

            if (!empty($rule_data)) {
                $privilege->ruleName = $this->createRule($rule_data['name'], $rule_data['class'])->name;
            }

            if ($force && $this->authManager->{$getter}($name) !== null) {
                echo "Force updating '$name'..." . PHP_EOL;
                if (!$this->authManager->update($name, $privilege)) {
                    throw new ErrorException('Cannot update ' . mb_strtolower($type_name) . ' ' . $name);
                }
            } else {
                echo "Adding '$name'..." . PHP_EOL;
                if (!$this->authManager->add($privilege)) {
                    throw new ErrorException('Cannot create ' . mb_strtolower($type_name) . ' ' . $name);
                }
            }
        } else {
            $msg = "$type_name '$name' already exists" . PHP_EOL;
            throw new ErrorException($msg);
        }

        return $this->authManager->{$getter}($name);
    }

    private function removePrivileges($privileges)
    {
        foreach ($privileges AS $privilege) {
            $item_type = ($privilege['type'] === Item::TYPE_ROLE ? 'Role' : 'Permission');
            $item_name = $privilege['name'];

            if (isset($privilege['_exists'])) {
                echo "Skipped '$item_name' (marked exists)" . PHP_EOL;
            } else {
                $privilegeObj = $this->authManager->{'create' . $item_type}($item_name);
                if (!$this->authManager->remove($privilegeObj)) {
                    throw new Exception("Can not remove '$item_name'");
                }
                echo "Removed '$item_name'" . PHP_EOL;
            }

            $this->removePrivileges($privilege['children'] ?? []);
        }
    }


    /**
     * Creates rule by given parameters
     * @param string $name
     * @param string $class
     * @return \yii\rbac\Rule|null
     * @throws \Exception
     */
    private function createRule($name, $class)
    {
        if ($this->authManager->getRule($name) === null) {
            $result = $this->authManager->add(new $class([
                                                             'name' => $name,
                                                         ]));
            if (!$result) {
                throw new \Exception('Can not create rule');
            }
        }
        return $this->authManager->getRule($name);
    }
}
