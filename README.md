Yii 2 Database Toolkit
======================

[![Latest Stable Version](https://poser.pugx.org/dmstr/yii2-db/v/stable.svg)](https://packagist.org/packages/dmstr/yii2-db) 
[![Total Downloads](https://poser.pugx.org/dmstr/yii2-db/downloads.svg)](https://packagist.org/packages/dmstr/yii2-db)
[![License](https://poser.pugx.org/dmstr/yii2-db/license.svg)](https://packagist.org/packages/dmstr/yii2-db)

About
-----

- Database extensions
- RBAC migrations moved to https://github.com/dmstr/yii2-rbac-migration since 2.0.0
- Active record access classes moved to https://github.com/dmstr/yii2-active-record-permissions since 2.0.0


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

---

Built by [dmstr](http://diemeisterei.de)
