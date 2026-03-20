<?php

declare(strict_types=1);

function pmsReadEnvValue(array $keys): ?string
{
    foreach ($keys as $key) {
        $value = getenv((string)$key);
        if ($value === false) {
            continue;
        }
        $value = trim((string)$value);
        if ($value !== '') {
            return $value;
        }
    }
    return null;
}

/**
 * Allow runtime override through env vars so DB name is not tied to config.php.
 * Supported keys (first non-empty wins):
 * - host: PMS_DB_HOST, DB_HOST
 * - port: PMS_DB_PORT, DB_PORT
 * - database: PMS_DB_DATABASE, PMS_DB_NAME, DB_NAME, MYSQL_DATABASE
 * - username: PMS_DB_USERNAME, PMS_DB_USER, DB_USER, MYSQL_USER
 * - password: PMS_DB_PASSWORD, PMS_DB_PASS, DB_PASS, MYSQL_PASSWORD
 * - charset: PMS_DB_CHARSET, DB_CHARSET
 *
 * @param array<string,mixed> $config
 * @return array<string,mixed>
 */
function pmsApplyEnvOverrides(array $config): array
{
    $overrides = array(
        'host' => pmsReadEnvValue(array('PMS_DB_HOST', 'DB_HOST')),
        'port' => pmsReadEnvValue(array('PMS_DB_PORT', 'DB_PORT')),
        'database' => pmsReadEnvValue(array('PMS_DB_DATABASE', 'PMS_DB_NAME', 'DB_NAME', 'MYSQL_DATABASE')),
        'username' => pmsReadEnvValue(array('PMS_DB_USERNAME', 'PMS_DB_USER', 'DB_USER', 'MYSQL_USER')),
        'password' => pmsReadEnvValue(array('PMS_DB_PASSWORD', 'PMS_DB_PASS', 'DB_PASS', 'MYSQL_PASSWORD')),
        'charset' => pmsReadEnvValue(array('PMS_DB_CHARSET', 'DB_CHARSET'))
    );

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            continue;
        }
        if ($key === 'port') {
            $config[$key] = (int)$value;
        } else {
            $config[$key] = $value;
        }
    }

    return $config;
}
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
        throw new RuntimeException('Missing DB config file. Provide config.php or config.local.php.');
    }

    /** @var array<string, mixed> $config */
    $config = require $configPath;
    $config = pmsApplyEnvOverrides($config);

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
