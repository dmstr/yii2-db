<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2016 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\db\traits;

use yii\helpers\ArrayHelper;

/**
 * Trait ActiveRecordAccessTrait
 *
 * @property integer $access_owner
 * @property string $access_read
 * @property string $access_update
 * @property string $access_delete
 * @property string $access_domain
 *
 *
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
    private static $_public = '*';

    /**
     * @var array with access field names
     */
    private static $_availableAccessColumns = [
        'access_owner',
        'access_read',
        'access_update',
        'access_delete',
        'access_domain',
    ];

    /**
     * @inheritdoc
     */
    public static function find()
    {
        /** @var $query \yii\db\ActiveQuery */
        $query = parent::find();

        if (self::$activeAccessTrait) {
            // access owner check
            $query->where(['access_owner' => \Yii::$app->user->id]);

            // access read check
            foreach (array_keys(self::getUsersAuthItems()) as $authItem) {
                $query->orWhere('FIND_IN_SET("' . $authItem . '", access_read)');
            }

            // access domain check
            $query->andWhere(['access_domain' => \Yii::$app->language]);
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return ArrayHelper::merge(
            parent::rules(),
            [
                [['access_owner', 'access_domain', 'access_read', 'access_update', 'access_delete'], 'safe'],
                [['access_domain', 'access_read', 'access_update', 'access_delete'], 'string', 'max' => 255],
                [['access_domain', 'access_read', 'access_update', 'access_delete'], 'default', 'value' => null],
                [['access_domain'], 'default', 'value' => \Yii::$app->language],
                [['access_owner'], 'integer'],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        parent::beforeSave($insert);

        // return true for new records
        if ($insert) {
            if (!\Yii::$app->user->isGuest) {
                $this->access_owner = \Yii::$app->user->id;
            }
            return true;
        }

        if (self::$activeAccessTrait) {
            if (!$this->hasPermission('access_update')) {
                $this->addAccessError('update');
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        parent::beforeDelete();

        if (self::$activeAccessTrait) {
            if (!$this->hasPermission('access_delete')) {
                $this->addAccessError('delete');
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * @return array to identify all access
     */
    public static function allAccess()
    {
        return [self::$_public => self::$_public];
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

            if (\Yii::$app->user->identity->isAdmin) {

                // All roles
                foreach ($authManager->getRoles() as $name => $role) {

                    if (!empty($role->description)) {
                        $description = $role->description;
                    } else {
                        $description = $name;
                    }
                    $authRoles[$name] = $description;
                }

                // All permissions
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

                    $authItems[$name] = $authManager->getItem($item->roleName)->description;

                    // add one child layer
                    foreach ($authManager->getChildren($item->roleName) as $childName => $childItem) {
                        $authItems[$childName] = $authManager->getItem($childItem->name)->description;
                    }
                }
            }
            $items = array_merge($publicAuthItem, $authItems);
            return $items;
        }
        return $publicAuthItem;
    }

    /**
     * For use with yii2-giiant OptsProvider
     * @return array available access domains
     */
    public static function optsAccessDomain()
    {
        $languages = self::allAccess();
        foreach (\Yii::$app->urlManager->languages as $language) {
            $languages[$language] = $language;
        }

        return $languages;
    }

    /**
     * For use with yii2-giiant OptsProvider
     * @return array available read accesses
     */
    public static function optsAccessRead()
    {
        return self::getUsersAuthItems();
    }

    /**
     * For use with yii2-giiant OptsProvider
     * @return array available update accesses
     */
    public static function optsAccessUpdate()
    {
        return self::getUsersAuthItems();
    }

    /**
     * For use with yii2-giiant OptsProvider
     * @return array available delete accesses
     */
    public static function optsAccessDelete()
    {
        return self::getUsersAuthItems();
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
        if (!in_array($action, self::$_availableAccessColumns)) {
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
        if (!in_array($action, self::$_availableAccessColumns)) {
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
        if ($action === null && !in_array($action, self::$_availableAccessColumns)) {
            return false;
        }
        // owner check
        if (!\Yii::$app->user->isGuest && $this->access_owner === \Yii::$app->user->id) {
            return true;
        }
        // check assigned permissions
        if (!empty(array_intersect(array_keys(self::getUsersAuthItems()), explode(',', $this->{$action})))) {
            return true;
        }

        return false;
    }

    /**
     * Set error flash for controller action id
     *
     * @param string $action
     *
     * @return bool|false
     */
    private function addAccessError($action)
    {
        $msg = \Yii::t(
            'app',
            'You are not allowed to {0} record #{1}',
            [$action, $this->primaryKey]
        );

        if (self::$enableFlashMessages) {
            \Yii::$app->session->addFlash(
                'danger',
                $msg
            );
        } else {
            \Yii::error($msg, __METHOD__);
        }
        return false;
    }
}
