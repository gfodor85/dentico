<?php

declare(strict_types=1);

/**
 * Creates a PDO connection using the local config/database.php file.
 */
function denticoDatabase(): PDO
{
    $configPath = dirname(__DIR__) . '/config/database.php';

    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Database configuration is missing. Copy config/database.example.php to config/database.php and update the settings.'
        );
    }

    $config = require $configPath;

    foreach (['host', 'database', 'username', 'password', 'charset'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Missing database configuration value: ' . $key);
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset']
    );

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
