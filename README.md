Yii 2 Database Toolkit
======================

[![Latest Stable Version](https://poser.pugx.org/dmstr/yii2-db/v/stable.svg)](https://packagist.org/packages/dmstr/yii2-db) 
[![Total Downloads](https://poser.pugx.org/dmstr/yii2-db/downloads.svg)](https://packagist.org/packages/dmstr/yii2-db)
[![License](https://poser.pugx.org/dmstr/yii2-db/license.svg)](https://packagist.org/packages/dmstr/yii2-db)

About
-----


### [dmstr\db\behaviors\HydratedAttributes](https://github.com/dmstr/yii2-db/blob/master/db/behaviors/HydratedAttributes.php)

Retrieves all eager loaded attributes of a model including relations. Once the extension is installed, simply use it in your code by accessing the corresponding classes by their full namespaced path.

### [dmstr\db\mysql\FileMigration](https://github.com/dmstr/yii2-db/blob/master/db/mysql/FileMigration.php)

runs database migrations from `sql` files


- Generic database exentsions
  - Hydrated Attributes
- Database extensions for MySQL
  - File Migration
  - Mysql dump/export/import console controller
- RBAC migrations moved to https://github.com/dmstr/yii2-rbac-migration since 2.0.0
- Active record access classes moved to https://github.com/dmstr/yii2-active-record-permissions since 2.0.0


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist dmstr/yii2-db "*"
```

or add

```
"dmstr/yii2-db": "*"
```

to the require section of your `composer.json` file.

Configuration
-------------

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

Usage
-----

### Commands

#### `yii migrate ...`

Create a file migration class

```
yii migrate/create \
    --templateFile='@vendor/dmstr/yii2-db/db/mysql/templates/file-migration.php' init_dump
```


#### `yii db ...`

```
DESCRIPTION

MySQL database maintenance command for current (db) connection


SUB-COMMANDS

- db/create               Create schema
- db/destroy              Remove schema
- db/dump                 Dump schema (all tables)
- db/export               Export tables (INSERT only)
- db/import               Import from file to database and flush cache
- db/index (default)      Displays tables in database
- db/wait-for-connection

To see the detailed information about individual sub-commands, enter:

  yii help <sub-command>

```


Show help

```
yii help db
```


### Examples

Dry-run command (not available for all commands)

```
yii db/create root secret -n
```

Destroy database

```
yii db/destroy root secret
```

Dump all tables

```
yii db/dump -o /dumps
```

Dump from different connection, exclude logging tables

``` 
yii db/dump -o /dumps \
  --db=dbReadonly \
  --noDataTables=app_audit_data,app_audit_entry,app_audit_error,app_audit_javascript,app_audit_mail
```

Dump from secondary connection, import into primary (default)

```
yii db/dump -o /dumps   \
    --db=dbReadonly   \
    --noDataTables=app_audit_data,app_audit_entry,app_audit_error,app_audit_javascript,app_audit_mail \
 | xargs yii db/import --interactive=0
```


---

Built by [dmstr](http://diemeisterei.de)
