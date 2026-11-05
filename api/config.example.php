<?php
/**
 * Example database settings for the PHP/MySQL installation.
 *
 * Copy this file to config.php on the PHP host, fill in the values from your
 * hosting control panel, and keep config.php out of version control. The
 * application does not connect to this file until a PHP API is installed and
 * wired into index.html.
 */
return [
    'db' => [
        // Usually localhost on shared hosting. Use the exact host from cPanel.
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'YOUR_CPANEL_DATABASE_NAME',
        'user' => 'YOUR_CPANEL_DATABASE_USER',
        'password' => 'CHANGE_THIS_TO_YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
