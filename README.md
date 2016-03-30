Yii 2 Database Toolkit
======================

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

**/!\ EXPERIMENTAL /!\**


Only include specific tables (schema & data dump) 

* `yii db/x-dump --includeTables=table_1,table,2,table_3,...`


Only include specific tables (data dump)

* `yii db/x-dump --includeTables=table_1,table,2,table_3,... --dataOnly=1`


Dump all tables excluding specific tables (schema & data dump) 

* `yii db/x-dump --excludeTables=table_1,table,2,table_3,...`


Dump all tables excluding specific tables (data dump) 

* `yii db/x-dump --excludeTables=table_1,table,2,table_3,... --dataOnly=1`



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

### dmstr\console\controllers\MysqlControllers

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
