<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Database configuration TEMPLATE (safe to commit).
// This file is an example only. It must never hold real host passwords.
//
// Setup on every machine or host:
//   1. From the project root:  cp config/database.example.php config/database.php
//   2. Edit config/database.php with YOUR MySQL values for that environment
//   3. Leave this example file unchanged (placeholders stay in Git)
//
// config/database.php is listed in .gitignore and must never be pushed.
// Confirm with:  git check-ignore -v config/database.php
//
// Production tips (myweb.cs.uwindsor.ca and similar shared hosts):
//   - host is often "localhost"; sometimes the host gives a different MySQL hostname
//   - port is almost always 3306
//   - dbname / username / password come from the host control panel
//   - charset must stay utf8mb4 so it matches database/schema.sql

declare(strict_types=1);

return [
    // MySQL server hostname
    'host' => 'localhost',

    // MySQL port (change only if your host documents a different port)
    'port' => 3306,

    // Database name created for CustomCore on this environment
    'dbname' => 'your_database_name',

    // Database username for this environment
    'username' => 'your_database_username',

    // Database password for this environment (keep out of Git)
    'password' => 'your_database_password',

    // Character set for PDO connections (must match the schema collation family)
    'charset' => 'utf8mb4',

    /**
     * Optional PDO attribute overrides used by includes/database.php.
     * The connector always sets ERRMODE_EXCEPTION, FETCH_ASSOC, and
     * EMULATE_PREPARES = false. Leave this array empty unless a host
     * documents a required override.
     *
     * @var array<int, mixed>
     */
    'options' => [],
];
