<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2016 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\traits;
use Yii;
use dmstr\db\exceptions\UnsupportedDbException;


/**
 * Trait ActiveRecordAccessTrait
 *
 * @package dmstr\db\traits
 * @author Christopher Stebe <c.stebe@herzogkommunikation.de>
 */
trait ActiveRecordAccessTrait
{
    /**
     * Use session flash messages
     * @var bool
     */
    public static $enableFlashMessages = true;

    /**
     * Active find, beforeSave, beforeDelete
     * @var bool
     */
    public static $activeAccessTrait = true;

    /**
     * Public / all access
     * @var string
     */
    public static $_all = '*';

    /**
     * @return array with access field names
     */
    public static function accessColumnAttributes()
    {
        return [
            'owner'  => 'access_owner',
            'read'   => 'access_read',
            'update' => 'access_update',
            'delete' => 'access_delete',
            'domain' => 'access_domain',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        /** @var $query \yii\db\ActiveQuery */
        $query = parent::find();

        $accessOwner  = self::accessColumnAttributes()['owner'];
        $accessRead   = self::accessColumnAttributes()['read'];
        $accessDomain = self::accessColumnAttributes()['domain'];

        if (self::$activeAccessTrait) {

            // access owner check
            if ($accessOwner) {
                $query->where([$accessOwner => \Yii::$app->user->id]);
            }

            // access read check
            if ($accessRead) {
                $queryType = ($accessOwner) ? 'orWhere' : 'where';
                $authItems = implode(',', array_keys(self::getUsersAuthItems()));
                $checkInSetQuery = self::getInSetQueryPart($accessRead, $authItems);
                $query->$queryType($checkInSetQuery);
            }

            // access domain check
            if ($accessDomain) {
                $query->andWhere([$accessDomain => [\Yii::$app->language, self::$_all]]);
            }
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if (self::$activeAccessTrait) {
                if ($insert) {
                    // INSERT record: return true for new records
                    $accessOwner = self::accessColumnAttributes()['owner'];
                    if ($accessOwner && !\Yii::$app->user->isGuest) {
                        $this->$accessOwner = \Yii::$app->user->id;
                    }
                } else {
                    // UPDATE record
                    $accessUpdate = self::accessColumnAttributes()['update'];
                    if ($accessUpdate) {
                        if (!$this->hasPermission($accessUpdate)) {
                            $this->addAccessError('update', $accessUpdate);
                            return false;
                        }
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            if (self::$activeAccessTrait) {
                $accessDelete = self::accessColumnAttributes()['delete'];
                if ($accessDelete && !$this->hasPermission($accessDelete)) {
                    $this->addAccessError('delete', $accessDelete);
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return array to identify all access
     */
    public static function allAccess()
    {
        return [self::$_all => self::$_all];
    }

    /**
     * All assigned auth items for the logged in user or all available auth items for admin users
     * @return array with item names
     */
    public static function getUsersAuthItems()
    {
        // Public auth item, default
        $publicAuthItem = self::allAccess();

        if (!\Yii::$app->user->isGuest) {

            // auth manager
            $authManager = \Yii::$app->authManager;

            if (!empty(\Yii::$app->user->identity->isAdmin)) {

                // All roles
                $authRoles = [];
                foreach ($authManager->getRoles() as $name => $role) {

                    if (!empty($role->description)) {
                        $description = $role->description;
                    } else {
                        $description = $name;
                    }
                    $authRoles[$name] = $description;
                }

                // All permissions
                $authPermissions = [];
                foreach ($authManager->getPermissions() as $name => $permission) {

                    if (!empty($permission->description)) {
                        $description = $permission->description;
                    } else {
                        $description = $name;
                    }
                    $authPermissions[$name] = $description;
                }

                // All auth items
                $authItems = array_merge($authRoles, $authPermissions);
            } else {
                // Users auth items
                $authItems = [];
                foreach (\Yii::$app->authManager->getAssignments(\Yii::$app->user->id) as $name => $item) {

                    $authItem = $authManager->getItem($item->roleName);

                    if (!empty($authItem->description)) {
                        $description = $authItem->description;
                    } else {
                        $description = $name;
                    }
                    $authItems[$name] = $description;
                }
            }
            $items = array_merge($publicAuthItem, $authItems);
            asort($items);
            return $items;
        }
        return $publicAuthItem;
    }

    /**
     * Decode access column by action from csv to array
     *
     * @param string $action
     * @param array $authItems
     *
     * @return string|null
     */
    public function authItemArrayToString($action, array $authItems)
    {
        if (!in_array($action, self::accessColumnAttributes())) {
            return null;
        }

        $this->{$action} = implode(',', array_keys($authItems));
    }

    /**
     * Encode access column by action from csv to array
     * @param $action
     *
     * @return array|null
     */
    public function authItemStringToArray($action)
    {
        if (!in_array($action, self::accessColumnAttributes())) {
            return null;
        }
        $arr = explode(',', $this->$action);
        return array_combine($arr, $arr);
    }

    /**
     * Check permission for record
     *
     * @param null $action
     *
     * @return bool
     */
    public function hasPermission($action = null)
    {
        if ($action === null && !in_array($action, self::accessColumnAttributes())) {
            return false;
        }
        // always true for admins
        if (!\Yii::$app->user->isGuest && \Yii::$app->user->identity->isAdmin) {
            return true;
        }
        // owner check
        $accessOwner  = self::accessColumnAttributes()['owner'];
        if ($accessOwner) {
            if (!\Yii::$app->user->isGuest && $this->{$accessOwner} === \Yii::$app->user->id) {
                return true;
            }
        }
        // check assigned permissions
        if (!empty(array_intersect(array_keys(self::getUsersAuthItems()), explode(',', $this->{$action})))) {
            return true;
        }
        return false;
    }

    /**
     * - Set error flash for controller action id
     * - Add error message to access attribute
     * - Write error log
     *
     * @param string $action update|delete
     * @param string $attribute
     */
    private function addAccessError($action, $attribute)
    {
        // the error message
        $msg = \Yii::t(
            'app',
            'You are not allowed to {0} record #{1}',
            [$action, $this->primaryKey]
        );

        if (self::$enableFlashMessages) {
            \Yii::$app->session->addFlash('error', $msg);
        }
        $this->addError($attribute, $msg);
        \Yii::info('User ID: #' . \Yii::$app->user->id . ' | ' . $msg, get_called_class());
    }
    
    /**
     * Return correct part of check in set  query for current DB
     * @param $accessRead
     * @param $authItems
     * @return string
     */
    private static function getInSetQueryPart($accessRead, $authItems)
    {
        $dbName = Yii::$app->db->getDriverName();
        switch($dbName) {
            case 'mysql':
                return 'FIND_IN_SET(' . $accessRead . ', "' . $authItems . '") > 0';
            case 'pgsql':
                return " '" . $accessRead . "'= SOME (string_to_array('$authItems', ','))";
            default:
                throw new UnsupportedDbException('This database is not being supported yet');
        }
    }
}
