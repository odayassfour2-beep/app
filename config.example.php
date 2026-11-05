<?php
/** Copy to config.php on the host. Never commit config.php or real passwords. */
return [
    'db' => [
        'host' => 'REPLACE_WITH_MYSQL_HOST_FROM_HOSTING_PANEL',
        'port' => 3306,
        'name' => 'REPLACE_WITH_DATABASE_NAME',
        'user' => 'REPLACE_WITH_DATABASE_USER',
        'password' => 'REPLACE_WITH_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    // Generate a long random value for first-time setup, then remove setup.php.
    'install_key' => 'REPLACE_WITH_A_LONG_RANDOM_ONE_TIME_KEY',
];
