<?php


return [
    'aliases' => [
        '@dmstr/db' => '@vendor/dmstr/yii2-db',
        '@tests' => '@vendor/dmstr/yii2-db/tests'
    ],
    'components' => [
        'db' => [
            'tablePrefix' => 'app_',
        ],
        'urlManager' => [
            'enableDefaultLanguageUrlCode' => true,
            'languages' => ['de', 'en', 'fr']
        ],
    ],
    'params' => [
        'yii.migrations' => [
            '@vendor/dmstr/yii2-db/tests/migrations'
        ]
    ]
];