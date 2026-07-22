<?php
/**
 * CustomCore — Database configuration TEMPLATE.
 *
 * File responsibility:
 *   Provides a safe, non-secret example of the database settings array.
 *
 * Setup:
 *   1. Copy this file to config/database.php
 *   2. Replace the placeholder values with your real MySQL credentials
 *   3. Never commit config/database.php (it is listed in .gitignore)
 *
 * Security:
 *   This example file must not contain real passwords.
 *   Production error pages must not print these values.
 */

declare(strict_types=1);

return [
    // MySQL server hostname (often "localhost" on shared hosting)
    'host' => 'localhost',

    // MySQL port (default 3306 unless your host specifies otherwise)
    'port' => 3306,

    // Database name created for CustomCore
    'dbname' => 'your_database_name',

    // Database username
    'username' => 'your_database_username',

    // Database password — replace in database.php only; keep secrets out of Git
    'password' => 'your_database_password',

    // Character set for PDO connections
    'charset' => 'utf8mb4',

    /**
     * Optional PDO attribute overrides used by the connection helper (Commit 1.3).
     * Leave as-is unless you know your host requires a change.
     */
    'options' => [
        // Thrown exceptions instead of silent failures
        // PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION is set in the connector
    ],
];
