<?php

declare(strict_types=1);
/**
 * Returns a PDO instance configured for the MariaDB/MySQL connection.
 *
 * @throws RuntimeException When the config file is missing or invalid.
 * @throws PDOException When the database connection fails.
 */
function createDatabaseConnection(): PDO
{
    $configPath = __DIR__ . '/config.local.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/config.php';
    }
    if (!file_exists($configPath)) {
        throw new RuntimeException('Missing config.php. Copy config.example.php or review the tracked credentials.');
    }

    /** @var array<string, mixed> $config */
    $config = require $configPath;

    foreach (['host', 'port', 'database', 'username', 'password', 'charset'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException("Database configuration is missing the '{$key}' key.");
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
            "SET NAMES %s COLLATE %s_unicode_ci",
            $config['charset'],
            $config['charset']
        ),
    ];

    return new PDO($dsn, (string) $config['username'], (string) $config['password'], $options);
}
