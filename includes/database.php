<?php
/**
 * CustomCore — Reusable PDO database connection helper.
 *
 * File responsibility:
 *   Loads config/database.php, opens a single shared PDO connection, and
 *   reports failures without exposing credentials, DSN passwords, or stack
 *   traces to end users when debug mode is off.
 *
 * Authentication requirements:
 *   None. This file only creates a database handle. Page-level auth is separate.
 *
 * Usage:
 *   require_once __DIR__ . '/database.php';
 *   $pdo = customcore_pdo();
 *
 * Returns:
 *   customcore_pdo() returns a configured PDO instance.
 *
 * Security:
 *   - Requires config/database.php (gitignored); never reads secrets from Git.
 *   - Uses PDO exceptions internally; public messages stay generic in production.
 *   - Disables emulated prepares so statements are prepared on the server.
 */

declare(strict_types=1);

/**
 * Load the non-secret application configuration array.
 *
 * @return array<string, mixed>
 */
function customcore_app_config(): array
{
    static $app = null;

    if ($app === null) {
        $path = dirname(__DIR__) . '/config/app.php';
        if (!is_readable($path)) {
            throw new RuntimeException('Application configuration file is missing.');
        }

        /** @var array<string, mixed> $loaded */
        $loaded = require $path;
        $app = $loaded;

        if (!empty($app['timezone']) && is_string($app['timezone'])) {
            date_default_timezone_set($app['timezone']);
        }
    }

    return $app;
}

/**
 * Load the local database configuration array from config/database.php.
 *
 * @return array<string, mixed>
 */
function customcore_db_config(): array
{
    static $db = null;

    if ($db === null) {
        $path = dirname(__DIR__) . '/config/database.php';

        if (!is_readable($path)) {
            throw new RuntimeException(
                'Database configuration file is missing. Copy config/database.example.php '
                . 'to config/database.php and enter your MySQL credentials.'
            );
        }

        /** @var array<string, mixed> $loaded */
        $loaded = require $path;

        foreach (['host', 'dbname', 'username', 'password', 'charset'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $loaded)) {
                throw new RuntimeException('Database configuration is incomplete.');
            }
        }

        $db = $loaded;
    }

    return $db;
}

/**
 * Whether the application is allowed to show developer-oriented error detail.
 */
function customcore_is_debug(): bool
{
    $app = customcore_app_config();
    return !empty($app['debug']);
}

/**
 * Build a visitor-safe database error message.
 *
 * @param Throwable $exception The underlying connection or configuration failure.
 */
function customcore_database_error_message(Throwable $exception): string
{
    if (customcore_is_debug()) {
        // Debug may show the exception message but must still avoid dumping passwords.
        $message = $exception->getMessage();
        $message = preg_replace('/password=[^;]*/i', 'password=***', $message) ?? $message;
        return 'Database connection failed: ' . $message;
    }

    return 'The database is temporarily unavailable. Please try again later.';
}

/**
 * Return a shared PDO connection using prepared-statement-friendly settings.
 *
 * @return PDO Active database connection.
 *
 * @throws RuntimeException When configuration is missing or the connection fails.
 */
function customcore_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $config = customcore_db_config();
        customcore_app_config(); // apply timezone and ensure app config loads

        $host = (string) $config['host'];
        $port = isset($config['port']) ? (int) $config['port'] : 3306;
        $dbname = (string) $config['dbname'];
        $charset = (string) $config['charset'];
        $username = (string) $config['username'];
        $password = (string) $config['password'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $dbname,
            $charset
        );

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    } catch (RuntimeException $exception) {
        // Configuration problems already use safe, credential-free messages.
        throw $exception;
    } catch (Throwable $exception) {
        throw new RuntimeException(
            customcore_database_error_message($exception),
            0,
            $exception
        );
    }
}
