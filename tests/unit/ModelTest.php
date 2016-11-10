<?php

namespace dmstr\db\tests\unit;

use dektrium\user\models\User;
use dmstr\db\traits\ActiveRecordAccessTrait;
use Yii;
use yii\db\ActiveRecord;


/**
 * Class AccessTrait
 * @package dmstr\db\tests\unit
 * @author Christopher Stebe <c.stebe@herzogkommunikation.de>
 */
class AccessTrait extends \yii\codeception\DbTestCase
{
    public $appConfig = '/app/vendor/dmstr/yii2-db/tests/_config/unit.php';

    /**
     * Log in with username
     *
     * @param $username
     */
    private function login($username)
    {
        $user = User::findOne(['username' => $username]);
        \Yii::$app->user->login($user);
    }

    /**
     * Logout user and destroy session
     */
    private function logout()
    {
        \Yii::$app->user->logout();
    }

    /**
     * Test access read
     */
    public function testAccessRead()
    {
        // check 'en' records as public
        \Yii::$app->language = 'en';

        $authManager = \Yii::$app->authManager;

        // try to find public accessible products
        // expect 4
        $products = Product::find()->all();
        $this->assertEquals(4, count($products), 'Public: cannot read the 4 expected products');

        // try to find accessible products for user #2
        // expect 5
        $this->login('user');
        $products = Product::find()->all();
        $this->assertEquals(5, count($products), 'User: cannot read the 5 expected products');

        // assign Editor role to user #2
        $authManager->assign($authManager->getRole('Editor'), \Yii::$app->user->id);
        // try to find accessible products for user #2 as Editor
        // expect 6
        $products = Product::find()->all();
        $this->assertEquals(6, count($products), 'User as Editor: cannot read the 6 expected products');
        $authManager->revoke($authManager->getRole('Editor'), \Yii::$app->user->id);

        $this->logout();
    }

    /**
     * Test access update
     */
    public function testAccessUpdate()
    {
        $authManager = \Yii::$app->authManager;

        // try to update product #1 as public
        // expect true
        $product        = Product::findOne(1);
        $product->title = 'Product updated by public user';
        $this->assertTrue($product->save(), 'Public: cannot update product #1');

        // try to update product #3 as public
        // expect false
        $product        = Product::findOne(3);
        $product->title = 'Product updated by public user';
        $this->assertFalse($product->save(), 'Public: should not be able to update product #3');

        // Login as user #2
        $this->login('user');

        // try to update product #3 as user #2
        // expect false
        $product        = Product::findOne(3);
        $product->title = 'Product updated by user #2';
        $this->assertFalse($product->save(), 'User: should not be able to update product #3');

        // assign Editor role to user #2
        $authManager->assign($authManager->getRole('Editor'), \Yii::$app->user->id);
        // try to update product #3 as user #2
        // expect true
        $product        = Product::findOne(3);
        $product->title = 'Product updated by user #2 as Editor';
        $this->assertTrue($product->save(), 'User: should not be able to update product #3');
        $authManager->revoke($authManager->getRole('Editor'), \Yii::$app->user->id);

        $this->logout();
    }


    /**
     * Test access owner
     */
    public function testAccessOwner()
    {
        // try to find, update and delete product #5 with user #2
        $this->login('user');

        // read expect true
        $product = Product::findOne(5);
        $this->assertEquals(1, count($product), 'User: cannot read product #5 as owner');

        // update expect true
        $product->title = 'Product updated by user #2 with only owner access';
        $this->assertTrue($product->save(), 'User: should be able to update product #5');

        // delete expect equals 1 deleted row
        $product = Product::findOne(5);
        $this->assertEquals(1, $product->delete(), 'User: should be able to delete product #5');

        // re-add product #5
        $restoreProduct                = new Product();
        $restoreProduct->id            = 5;
        $restoreProduct->title         = 'Product';
        $restoreProduct->access_domain = 'en';
        $restoreProduct->access_read   = 'Supervisor';
        $restoreProduct->access_update = 'Supervisor';
        $restoreProduct->access_delete = 'Supervisor';
        $restoreProduct->save();

        $this->logout();
    }

    /**
     * Test access domain
     */
    public function testAccessDomain()
    {
        // check 'de' records as public
        \Yii::$app->language = 'de';
        $authManager         = \Yii::$app->authManager;

        // expect 1
        $products = Product::find()->all();
        $this->assertEquals(1, count($products), 'Public: cannot read the 1 expected product for "de"');

        // check 'de' records as user as Editor
        $this->login('user');
        // assign Editor role to user #2
        $authManager->assign($authManager->getRole('Editor'), \Yii::$app->user->id);
        $products = Product::find()->all();
        $this->assertEquals(2, count($products), 'User: cannot read the 2 expected products for "de"');
        $authManager->revoke($authManager->getRole('Editor'), \Yii::$app->user->id);
        $this->logout();

        // check 'en' records as public
        \Yii::$app->language = 'en';

        // expect 4
        $products = Product::find()->all();
        $this->assertEquals(4, count($products), 'Public: cannot read the 4 expected products for "en"');

        // check 'en' records as public
        \Yii::$app->language = 'fr';

        // expect 1
        $products = Product::find()->all();
        $this->assertEquals(1, count($products), 'Public: cannot read the 1 expected product for "fr"');
    }

    /**
     * Test access delete
     */
    public function testAccessDelete()
    {
        // check 'en' records
        \Yii::$app->language = 'en';
        $authManager         = \Yii::$app->authManager;

        // try to delete product #4
        // expect not equals 1 deleted row
        $product = Product::findOne(4);
        $this->assertNotEquals(1, $product->delete(), 'Public: should not be able to delete product #4');

        // try to delete product #4 with user #2
        // expect not equals 1 deleted row
        $this->login('user');
        $product = Product::findOne(4);
        $this->assertNotEquals(1, $product->delete(), 'User: should not be able to delete product #4');

        // assign Editor role to user #2
        $authManager->assign($authManager->getRole('Editor'), \Yii::$app->user->id);
        // try to delete product #4 with user #2 as Editor
        // expect true
        $product = Product::findOne(4);
        $this->assertEquals(1, $product->delete(), 'User: should be able to update product #4 as "Editor"');
        $authManager->revoke($authManager->getRole('Editor'), \Yii::$app->user->id);

        $this->logout();

        // re-add product #4
        $this->login('admin');
        $restoreProduct                = new Product();
        $restoreProduct->id            = 4;
        $restoreProduct->title         = 'Product';
        $restoreProduct->access_domain = 'en';
        $restoreProduct->access_read   = '*';
        $restoreProduct->access_update = '*';
        $restoreProduct->access_delete = 'Editor';
        $restoreProduct->save();

        $this->logout();
    }
}

/**
 * This is the model class for table "product".
 *
 * @property integer $id
 * @property string $title
 *
 */
class Product extends ActiveRecord
{
    use ActiveRecordAccessTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'product';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'    => Yii::t('test-yii2-db', 'ID'),
            'title' => Yii::t('test-yii2-db', 'Title'),
        ];
    }
}
