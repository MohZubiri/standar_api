<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Dynamic per-university connection; database name set at runtime
        'mysql1' => [
            'driver' => 'mysql',
            'url' => env('DB1_URL', env('DB_URL')),
            'host' => env('DB1_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB1_PORT', env('DB_PORT', '3306')),
            'database' => env('DB1_DATABASE', ''),
            'username' => env('DB1_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB1_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB1_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('DB1_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('DB1_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB1_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        // Static secondary connection used by Eloquent models
        'mysql2' => [
            'driver' => 'mysql',
            'url' => env('DB2_URL', env('DB_URL')),
            'host' => env('DB2_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB2_PORT', env('DB_PORT', '3306')),
            'database' => env('DB2_DATABASE', 'financial'),
            'username' => env('DB2_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB2_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB2_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('DB2_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('DB2_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB2_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        // Center database, database name may be switched at runtime
        'center' => [
            'driver' => 'mysql',
            'url' => env('CENTER_DB_URL', env('DB_URL')),
            'host' => env('CENTER_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('CENTER_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('CENTER_DB_DATABASE', ''),
            'username' => env('CENTER_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('CENTER_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('CENTER_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('CENTER_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('CENTER_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('CENTER_DB_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        // Logging database connection for request/payment logs
        'logConnection' => [
            'driver' => 'mysql',
            'url' => env('LOG_DB_URL', env('DB_URL')),
            'host' => env('LOG_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('LOG_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('LOG_DB_DATABASE', 'logs'),
            'username' => env('LOG_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('LOG_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('LOG_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('LOG_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('LOG_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('LOG_DB_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
