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

        // access read check
        $query->where(['or like', 'access_read', array_keys(self::getUsersAuthItems())]);

        // owner check
        $query->orWhere(['access_owner' => \Yii::$app->user->id]);

        // access domain check
        $query->andWhere(['or like', 'access_domain', array_keys(self::acceptedAccessDomain())]);

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
                [['access_domain', 'access_read', 'access_update', 'access_delete'], 'default', 'value' => self::mask(self::$_public)],
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
            $this->access_owner = \Yii::$app->user->id;
            return true;
        }

        if (!$this->grandPermission('access_update')) {
            $this->addAccessError('update');
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

        if (!$this->grandPermission('access_delete')) {
            $this->addAccessError('delete');
        } else {
            return true;
        }
    }

    /**
     * @return array to identify all access
     */
    public static function allAccess()
    {
        return [self::mask(self::$_public) => self::$_public];
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
                    $authRoles[self::mask($name)] = $description;
                }

                // All permissions
                foreach ($authManager->getPermissions() as $name => $permission) {

                    if (!empty($permission->description)) {
                        $description = $permission->description;
                    } else {
                        $description = $name;
                    }
                    $authPermissions[self::mask($name)] = $description;
                }

                // All auth items
                $authItems = array_merge($authRoles, $authPermissions);

            } else {

                // Users auth items
                $authItems = [];
                foreach (\Yii::$app->authManager->getAssignments(\Yii::$app->user->id) as $name => $item) {
                    $authItems[self::mask($name)] = $authManager->getItem($item->roleName)->description;
                }
            }

            return array_merge($publicAuthItem, $authItems);
        }
        return $publicAuthItem;
    }

    /**
     * Currrent application language and * for all access_domains
     * @return array
     */
    public static function acceptedAccessDomain()
    {
        return ArrayHelper::merge([self::mask(\Yii::$app->language) => \Yii::$app->language], self::allAccess());
    }

    /**
     * For use with yii2-giiant OptsProvider
     * @return array available access domains
     */
    public static function optsAccessDomain()
    {
        $languages = self::allAccess();
        foreach (\Yii::$app->urlManager->languages as $language) {
            $languages[self::mask($language)] = $language;
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
     * Decode items from array to csv
     * @param $itemArray
     *
     * @return string
     */
    public function authItemArrayToString($itemArray)
    {
        return implode(',', array_keys($itemArray));
    }

    /**
     * Encode item from csv to array
     * @param $itemString
     *
     * @return array
     */
    public function authItemStringToArray($itemString)
    {
        $arr = [];
        foreach (explode(',', $itemString) as $item) {
            $arr[$item] = self::unmask($item);
        }

        return $arr;
    }

    /**
     * Check permission for record
     * @param null $action
     *
     * @return bool
     */
    private function grandPermission($action = null)
    {
        if ($action === null && !in_array($action, self::$_availableAccessColumns)) {
            return false;
        }
        // owner check
        if ($this->access_owner == \Yii::$app->user->id) {
            return true;
        }
        // check assigned permissions
        if (empty(array_intersect(array_keys(self::getUsersAuthItems()), explode(',', $this->{$action})))) {
            return false;
        }
        return true;
    }

    /**
     * Mask a given string into {$item}
     * @param $item
     *
     * @return string
     */
    private static function mask($item)
    {
        return '{' . $item . '}';
    }

    /**
     * Unmask a given string from {$item} to $item
     * @param $item
     *
     * @return string
     */
    private static function unmask($item)
    {
        return str_replace('{', '', str_replace('}', '', $item));
    }

    /**
     * Set error flash for controller action id
     * @param string $action
     *
     * @return bool|false
     */
    private function addAccessError($action)
    {
        \Yii::$app->session->addFlash(
            'danger',
            \Yii::t(
                'app',
                'You are not allowed to {0} record #{1}',
                [$action, $this->primaryKey]
            )
        );
        return false;
    }
}