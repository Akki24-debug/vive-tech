<?php

declare(strict_types=1);

require __DIR__ . '/connection.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Attempting database connection...\n";

try {
    $pdo = createDatabaseConnection();
    echo "Connection established successfully.\n";

    $statement = $pdo->query('SELECT 1 AS connectivity_check, @@version AS server_version');
    $result = $statement->fetch();

    if ($result !== false && isset($result['connectivity_check'])) {
        echo "Test query returned: " . $result['connectivity_check'] . "\n";
    } else {
        echo "Test query executed, but no results were returned.\n";
    }

    if ($result !== false && isset($result['server_version'])) {
        echo "Database server version: " . $result['server_version'] . "\n";
    }

    echo "You are ready to run application queries.\n";
} catch (Throwable $exception) {
    http_response_code(500);
    echo "Failed to connect or run test query.\n";
    echo 'Error: ' . $exception->getMessage() . "\n";
    exit(1);
}
