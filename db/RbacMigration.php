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
 *          'name' => 'Role_0',
 *          'type' => Item::TYPE_ROLE,
 *          'children' => [
 *              [
 *                  'name' => 'permission_0',
 *                  'type' => Item::TYPE_PERMISSION
 *              ],
 *              [
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
     * Generate privileges recursively
     *
     * @param array $privileges
     * @throws \yii\base\Exception
     * @throws ErrorException
     */
    protected function generatePrivileges($privileges = [])
    {
        foreach ($privileges as $privilege) {
            $parent_privilege = $this->createPrivilege($privilege['name'], $privilege['type'],
                                                       $privilege['rule'] ?? []);


            if (isset($privilege['children']) && \is_array($privilege['children'])) {
                foreach ($privilege['children'] as $child_privilege) {
                    $created_child_privilege = $this->createPrivilege($child_privilege['name'],
                                                                      $child_privilege['type'],
                                                                      $child_privilege['rule'] ?? [] );

                    // check if parent already has child or if parent can have this as a child
                    if (!$this->authManager->hasChild($parent_privilege,
                                                      $created_child_privilege) && $this->authManager->canAddChild($parent_privilege,
                                                                                                                   $created_child_privilege)) {
                        // add child to parent
                        if (!$this->authManager->addChild($parent_privilege, $created_child_privilege)) {
                            throw new ErrorException('Cannot add ' . $child_privilege['name'] . ' to ' . $privilege['name']);
                        }
                    }
                }
                $this->generatePrivileges($privilege['children']);
            }
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
    protected function createPrivilege($name, $type, $rule_data = [])
    {
        $type_name = ($type === Item::TYPE_ROLE ? 'Role' : 'Permission');

        $getter = 'get' . $type_name;

        // check if permission or role exists and create it
        if ($this->authManager->{$getter}($name) === null) {
            echo "Creating $type_name: $name".PHP_EOL;
            $privilege = $this->authManager->{'create' . $type_name}($name);

            if (!empty($rule_data)) {
                echo "Creating rule...".PHP_EOL;
                $privilege->ruleName = $this->createRule($rule_data['name'], $rule_data['class'])->name;
            }

            if (!$this->authManager->add($privilege)) {
                throw new ErrorException('Cannot create ' . mb_strtolower($type_name) . ' ' . $name);
            }
        } else {
            echo "$name exists [skipping]".PHP_EOL;
        }

        return $this->authManager->{$getter}($name);
    }

    /**
     * @return false
     */
    public function safeDown()
    {
        echo static::class . ' cannot be reverted.';
        return false;
    }

    /**
     * Creates rule by given parameters
     * @param string $name
     * @param string $class
     * @return \yii\rbac\Rule|null
     * @throws \Exception
     */
    protected function createRule($name, $class)
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
