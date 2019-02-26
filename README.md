Yii 2 Database Toolkit
======================

[![Latest Stable Version](https://poser.pugx.org/dmstr/yii2-db/v/stable.svg)](https://packagist.org/packages/dmstr/yii2-db) 
[![Total Downloads](https://poser.pugx.org/dmstr/yii2-db/downloads.svg)](https://packagist.org/packages/dmstr/yii2-db)
[![License](https://poser.pugx.org/dmstr/yii2-db/license.svg)](https://packagist.org/packages/dmstr/yii2-db)


Database extensions

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist dmstr/yii2-db "*"
```

or add

```
"dmstr/yii2-db": "*"
```

to the require section of your `composer.json` file.

Commands
--------

### `yii db`

```
DESCRIPTION

MySQL database maintenance command.


SUB-COMMANDS

- db/create               Create MySQL database
- db/destroy              Remove the current schema
- db/dump                 Dumps current database tables to runtime folder
- db/export               export data tables, without logs and caches
- db/import
- db/index (default)      Displays tables in database
- db/wait-for-connection

To see the detailed information about individual sub-commands, enter:

  yii help <sub-command>
```


Usage
-----

### [dmstr\db\behaviors\HydratedAttributes](https://github.com/dmstr/yii2-db/blob/master/db/behaviors/HydratedAttributes.php)

Retrieves all eager loaded attributes of a model including relations. Once the extension is installed, simply use it in your code by accessing the corresponding classes by their full namespaced path.

### [dmstr\db\mysql\FileMigration](https://github.com/dmstr/yii2-db/blob/master/db/mysql/FileMigration.php)

runs database migrations from `sql` files

Create a file migration class

```
./yii migrate/create \
    --templateFile='@vendor/dmstr/yii2-db/db/mysql/templates/file-migration.php' init_dump
```

### [dmstr\console\controllers](https://github.com/dmstr/yii2-db/blob/master/console/controllers)

Include it in your console configuration

```
   'controllerMap' => [
        'db'         => [
            'class' => 'dmstr\console\controllers\MysqlController',
            'noDataTables' => [
                'app_log',
                'app_session',
            ]
        ],
    ],
```

Show help

```
./yii help db
```

Available commands
  
```
DESCRIPTION

MySQL database maintenance command.


SUB-COMMANDS

- db/create           Create MySQL database from ENV vars and grant permissions
- db/dump             Dumps current database tables to runtime folder
- db/index (default)  Displays tables in database
```

Traits
---

### [dmstr\db\traits\ActiveRecordAccessTrait](https://github.com/dmstr/yii2-db/blob/master/db/traits/ActiveRecordAccessTrait.php)

**Option 1:**

How to equip your active record model with access control

- Use update migration in `db/migrations/m160609_090908_add_access_columns`

    - set all `$tableNames` to be updated and run migration

This migrations adds the available access check columns to your database table(s)

```
'access_owner',
'access_read',
'access_update',
'access_delete',
'access_domain',
```

- Add `use \dmstr\db\traits\ActiveRecordAccessTrait;` to your active record model

- *(update your cruds)*

### RBAC permissions

Permissions for selections

- `access.availableDomains:any`

Permissions to set default values

- `access.defaults.accessDomain:global`
- `access.defaults.updateDelete:<ROLE>`

**Option 2:**

Simply override this method in our AR model and set the access fields you have/want to the field names you have/want!

*Default:*
```
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
```

*Customize:*
```
public static function accessColumnAttributes()
{
    return [
        'owner'  => 'user_id',			// the column name with owner permissions
        'read'   => 'read_permission',	// the column name with read permissions
        'update' => false, 				// will do no access checks for update
        'delete' => false, 				// will do no access checks for delete
        'domain' => 'language',			// the column name with the access domain permission
    ];
}
```

**:secret: Congrats, you are now ready to manage specific access checks on your active records!**

:bulb: Access options:

- All access option `*`
- specific rbac roles and permissions assignable
    - single or multi
        - `*`
        - `Role1,Role2,Permission1,...`
        
- limit access to specific domain / language
    - `de` or `en`
        
- `Owner` gets all access over other given permissions
    - every active record can have exact one owner right which stands above `access_read`, `access_update`, `access_delete`

Planned updates:
---

- ActiveRecordAccessTrait
    -  in cruds use select2 multi for inputs (domain, read, update, delete)
        - Setter: authItemArrayToString()
        - Getter: authItemStringToArray()
        

---

Built by [dmstr](http://diemeisterei.de)
