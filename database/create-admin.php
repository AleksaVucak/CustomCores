<?php
/**
 * CustomCore — Secure Administrator Account Setup (Commit 2.7).
 *
 * File responsibility:
 *   Creates an admin user in the `users` table with a bcrypt-hashed password.
 *   Credentials are collected interactively on the command line — no plain-text
 *   passwords appear in this file, in seed files, or in Git history.
 *
 * Usage:
 *   php database/create-admin.php
 *
 * Prerequisites:
 *   1. Import database/schema.sql (users table must exist).
 *   2. Copy config/database.example.php → config/database.php and fill in
 *      your MySQL credentials (see config/README.md).
 *
 * Security:
 *   - CLI-only: refuses to run under a web server.
 *   - Uses password_hash() with PASSWORD_DEFAULT (bcrypt).
 *   - Password input is hidden on systems that support stty.
 *   - Never echoes or logs the entered password.
 *   - If the email already exists, offers to update the role instead of
 *     duplicating the account.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Block web access
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden: run this script from the command line only.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

require_once dirname(__DIR__) . '/includes/database.php';

/**
 * Read a line from STDIN with a prompt.
 */
function admin_prompt(string $label, bool $required = true): string
{
    echo $label;
    $value = trim((string) fgets(STDIN));

    if ($required && $value === '') {
        fwrite(STDERR, "Error: a value is required.\n");
        exit(1);
    }

    return $value;
}

/**
 * Read a password from STDIN, hiding input on supported terminals.
 */
function admin_prompt_password(string $label): string
{
    $sttyAvailable = (stripos(PHP_OS, 'WIN') === false);

    if ($sttyAvailable) {
        echo $label;
        system('stty -echo');
        $password = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
    } else {
        echo "(Note: password will be visible on Windows terminals.)\n";
        echo $label;
        $password = trim((string) fgets(STDIN));
    }

    if ($password === '') {
        fwrite(STDERR, "Error: password cannot be empty.\n");
        exit(1);
    }

    return $password;
}

// ---------------------------------------------------------------------------
// Collect credentials
// ---------------------------------------------------------------------------

echo "==========================================================\n";
echo "  CustomCore — Administrator Account Setup\n";
echo "==========================================================\n\n";

$email = admin_prompt('Admin email: ');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Error: '{$email}' is not a valid email address.\n");
    exit(1);
}

$firstName = admin_prompt('First name: ');
$lastName  = admin_prompt('Last name: ');

$password = admin_prompt_password('Password (min 8 characters): ');

if (strlen($password) < 8) {
    fwrite(STDERR, "Error: password must be at least 8 characters.\n");
    exit(1);
}

$confirm = admin_prompt_password('Confirm password: ');

if ($password !== $confirm) {
    fwrite(STDERR, "Error: passwords do not match.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Hash and insert
// ---------------------------------------------------------------------------

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = customcore_pdo();

    // Check if email is already registered
    $checkStmt = $pdo->prepare(
        'SELECT id, role FROM users WHERE email = :email LIMIT 1'
    );
    $checkStmt->execute([':email' => $email]);
    $existing = $checkStmt->fetch();

    if ($existing !== false) {
        $existingId   = (int) $existing['id'];
        $existingRole = (string) $existing['role'];

        if ($existingRole === 'admin') {
            echo "\nUser '{$email}' already exists as an admin (id {$existingId}).\n";
            echo "No changes made.\n";
            exit(0);
        }

        echo "\nUser '{$email}' exists as a '{$existingRole}' (id {$existingId}).\n";
        $upgrade = admin_prompt('Promote to admin? (yes/no): ');

        if (strtolower($upgrade) === 'yes') {
            $updateStmt = $pdo->prepare(
                'UPDATE users SET role = :role, password_hash = :hash WHERE id = :id'
            );
            $updateStmt->execute([
                ':role' => 'admin',
                ':hash' => $hash,
                ':id'   => $existingId,
            ]);
            echo "User promoted to admin and password updated.\n";
            exit(0);
        }

        echo "Aborted. No changes made.\n";
        exit(0);
    }

    // Insert new admin
    $insertStmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, role, is_active)
         VALUES (:email, :hash, :first, :last, :role, :active)'
    );

    $insertStmt->execute([
        ':email'  => $email,
        ':hash'   => $hash,
        ':first'  => $firstName,
        ':last'   => $lastName,
        ':role'   => 'admin',
        ':active' => 1,
    ]);

    $newId = (int) $pdo->lastInsertId();

    echo "\nAdmin account created successfully.\n";
    echo "  ID:    {$newId}\n";
    echo "  Email: {$email}\n";
    echo "  Name:  {$firstName} {$lastName}\n";
    echo "  Role:  admin\n";
    echo "\nPassword has been securely hashed (bcrypt). The plain-text\n";
    echo "password was never stored or logged.\n";
    exit(0);

} catch (Throwable $exception) {
    fwrite(STDERR, "\nError: " . $exception->getMessage() . "\n");
    exit(1);
}
