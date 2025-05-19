<?php

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/tests/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/tests/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => '',
        'mysql' => [
            'adapter' => 'mysql',
            'host' => getenv('TESTS_DB_MYSQL_HOSTNAME') ?: 'localhost',
            'name' => getenv('TESTS_DB_MYSQL_DATABASE') ?: 'tp_orm_test',
            'user' => getenv('TESTS_DB_MYSQL_USERNAME') ?: 'homestead',
            'pass' => getenv('TESTS_DB_MYSQL_PASSWORD') ?: 'secret',
            'port' => getenv('TESTS_DB_MYSQL_HOSTPORT') ?: '3306',
            'charset' => 'utf8',
        ],
        'pgsql' => [
            'adapter' => 'pgsql',
            'host' => getenv('TESTS_DB_PGSQL_HOSTNAME') ?: 'localhost',
            'name' => getenv('TESTS_DB_PGSQL_DATABASE') ?: 'tp_orm_test',
            'user' => getenv('TESTS_DB_PGSQL_USERNAME') ?: 'homestead',
            'pass' => getenv('TESTS_DB_PGSQL_PASSWORD') ?: 'secret',
            'port' => getenv('TESTS_DB_PGSQL_HOSTPORT') ?: '5432',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
