<?php
/**
 * CustomCore — CLI database connection test.
 *
 * File responsibility:
 *   Verifies that config/database.php works with includes/database.php.
 *   Intended for local/server command-line use only — not a public web page.
 *
 * Usage:
 *   php database/test-connection.php
 *
 * Security:
 *   Refuses to run through a normal web SAPI so credentials are not tested
 *   from a publicly reachable URL.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden: run this script from the command line only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/includes/database.php';

try {
    $pdo = customcore_pdo();
    $stmt = $pdo->query('SELECT 1 AS connected');
    $row = $stmt ? $stmt->fetch() : false;

    if ($row === false || (int) ($row['connected'] ?? 0) !== 1) {
        fwrite(STDERR, "Connection opened but test query failed.\n");
        exit(1);
    }

    $config = customcore_db_config();
    $host = (string) $config['host'];
    $dbname = (string) $config['dbname'];

    echo "CustomCore database connection: OK\n";
    echo 'Host: ' . $host . "\n";
    echo 'Database: ' . $dbname . "\n";
    echo "Credentials: not displayed\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'CustomCore database connection: FAILED' . PHP_EOL);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
