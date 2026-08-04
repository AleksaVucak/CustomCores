<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Production / local configuration hygiene check (CLI only).
// Verifies that secrets stay out of Git, that the real database config exists
// and is not still filled with example placeholders, and that production-facing
// app settings are safe. NEVER prints passwords, usernames, or full DSNs.
//
// Usage (from the project root):
//   php database/verify-config.php
//   php database/verify-config.php --production
//
// Exit codes: 0 = all checks passed, 1 = one or more failures, 2 = not CLI.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "database/verify-config.php is a CLI tool only.\n");
    exit(2);
}

$root = dirname(__DIR__);
$productionMode = in_array('--production', $argv, true);

$pass = 0;
$fail = 0;
$warn = 0;

/**
 * Print a pass line.
 */
function cc_check_ok(string $message): void
{
    global $pass;
    $pass++;
    echo '[OK]   ' . $message . PHP_EOL;
}

/**
 * Print a failure line.
 */
function cc_check_fail(string $message): void
{
    global $fail;
    $fail++;
    echo '[FAIL] ' . $message . PHP_EOL;
}

/**
 * Print a soft warning (does not fail the run by itself).
 */
function cc_check_warn(string $message): void
{
    global $warn;
    $warn++;
    echo '[WARN] ' . $message . PHP_EOL;
}

echo "CustomCore configuration verification" . PHP_EOL;
echo "Mode: " . ($productionMode ? 'production' : 'local') . PHP_EOL;
echo str_repeat('-', 52) . PHP_EOL;

// ---------------------------------------------------------------------------
// 1. Gitignore protects the real credentials file
// ---------------------------------------------------------------------------

$gitignorePath = $root . '/.gitignore';
if (!is_readable($gitignorePath)) {
    cc_check_fail('.gitignore is missing or unreadable.');
} else {
    $ignoreText = (string) file_get_contents($gitignorePath);
    if (preg_match('/(^|[\n\r])\/?config\/database\.php(\s|$)/m', $ignoreText) === 1) {
        cc_check_ok('.gitignore lists config/database.php');
    } else {
        cc_check_fail('.gitignore does not list config/database.php');
    }
}

// Prefer git check-ignore when git is available.
$checkIgnore = [];
$gitCode = 1;
if (is_dir($root . '/.git')) {
    exec(
        'git -C ' . escapeshellarg($root) . ' check-ignore -v config/database.php 2>/dev/null',
        $checkIgnore,
        $gitCode
    );
    if ($gitCode === 0 && $checkIgnore !== []) {
        cc_check_ok('git check-ignore confirms config/database.php is ignored');
    } else {
        cc_check_warn('git check-ignore did not report config/database.php (file may be missing locally)');
    }

    exec(
        'git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch config/database.php 2>/dev/null',
        $tracked,
        $trackedCode
    );
    if ($trackedCode === 0) {
        cc_check_fail('config/database.php is tracked by Git (remove it from the index immediately)');
    } else {
        cc_check_ok('config/database.php is not a tracked Git file');
    }
}

// ---------------------------------------------------------------------------
// 2. Example templates exist and hold only placeholders
// ---------------------------------------------------------------------------

$examplePath = $root . '/config/database.example.php';
if (!is_readable($examplePath)) {
    cc_check_fail('config/database.example.php is missing');
} else {
    /** @var array<string, mixed> $example */
    $example = require $examplePath;
    $placeholderHits = 0;
    foreach (['dbname', 'username', 'password'] as $key) {
        $value = isset($example[$key]) ? (string) $example[$key] : '';
        if (str_starts_with($value, 'your_')) {
            $placeholderHits++;
        }
    }
    if ($placeholderHits === 3) {
        cc_check_ok('database.example.php still uses placeholder credentials only');
    } else {
        cc_check_fail('database.example.php looks customized; restore placeholders (never commit real secrets)');
    }

    if (isset($example['charset']) && (string) $example['charset'] === 'utf8mb4') {
        cc_check_ok('database.example.php charset is utf8mb4');
    } else {
        cc_check_fail('database.example.php charset should be utf8mb4');
    }
}

$prodAppExample = $root . '/config/app.production.example.php';
if (!is_readable($prodAppExample)) {
    cc_check_fail('config/app.production.example.php is missing');
} else {
    /** @var array<string, mixed> $prodApp */
    $prodApp = require $prodAppExample;
    if (($prodApp['environment'] ?? null) === 'production' && empty($prodApp['debug'])) {
        cc_check_ok('app.production.example.php sets environment=production and debug=false');
    } else {
        cc_check_fail('app.production.example.php must use environment=production and debug=false');
    }
    if (isset($prodApp['password']) || isset($prodApp['dbname'])) {
        cc_check_fail('app.production.example.php must not contain database credentials');
    } else {
        cc_check_ok('app.production.example.php contains no database credential keys');
    }
}

// ---------------------------------------------------------------------------
// 3. Live app.php (non-secret) settings
// ---------------------------------------------------------------------------

$appPath = $root . '/config/app.php';
if (!is_readable($appPath)) {
    cc_check_fail('config/app.php is missing');
    $app = [];
} else {
    /** @var array<string, mixed> $app */
    $app = require $appPath;
    cc_check_ok('config/app.php is readable');

    if (array_key_exists('debug', $app) && $app['debug'] === false) {
        cc_check_ok('app.php debug is false (production-safe default)');
    } elseif (!empty($app['debug'])) {
        if ($productionMode) {
            cc_check_fail('app.php debug is true (must be false on the live host)');
        } else {
            cc_check_warn('app.php debug is true (fine locally; turn off before hosting)');
        }
    } else {
        cc_check_ok('app.php debug is off');
    }

    $env = isset($app['environment']) ? (string) $app['environment'] : '';
    if ($productionMode) {
        if ($env === 'production') {
            cc_check_ok("app.php environment is 'production'");
        } else {
            cc_check_fail("app.php environment should be 'production' on the live host (currently '" . $env . "')");
        }
    } else {
        cc_check_ok("app.php environment is '" . ($env !== '' ? $env : 'unset') . "'");
    }

    $paths = isset($app['paths']) && is_array($app['paths']) ? $app['paths'] : [];
    $requiredPaths = [
        'uploads_consultation',
        'uploads_products',
        'themes',
        'images',
        'media',
    ];
    $pathOk = true;
    foreach ($requiredPaths as $pathKey) {
        if (!isset($paths[$pathKey]) || !is_string($paths[$pathKey]) || $paths[$pathKey] === '') {
            $pathOk = false;
            cc_check_fail("app.php paths.{$pathKey} is missing");
            continue;
        }
        $rel = str_replace('\\', '/', $paths[$pathKey]);
        if (str_starts_with($rel, '/') || preg_match('#^[A-Za-z]:/#', $rel) === 1 || str_contains($rel, '..')) {
            $pathOk = false;
            cc_check_fail("app.php paths.{$pathKey} must be a project-relative path without '..'");
        }
    }
    if ($pathOk) {
        cc_check_ok('app.php storage paths are project-relative and complete');
    }

    $baseUrl = isset($app['base_url']) ? trim((string) $app['base_url']) : '';
    if ($baseUrl === '') {
        cc_check_ok('app.php base_url is empty (depth-safe relative URLs)');
    } elseif (preg_match('#^https?://#i', $baseUrl) === 1 && !str_ends_with($baseUrl, '/')) {
        cc_check_ok('app.php base_url is an absolute URL without a trailing slash');
    } else {
        cc_check_warn('app.php base_url should be empty or an absolute URL with no trailing slash');
    }

    // Guardrail: app.php must never grow credential keys by accident.
    foreach (['password', 'db_password', 'username', 'db_user', 'dbname'] as $forbidden) {
        if (array_key_exists($forbidden, $app)) {
            cc_check_fail("app.php unexpectedly contains credential-like key '{$forbidden}'");
        }
    }
}

// ---------------------------------------------------------------------------
// 4. Real database.php (gitignored) readiness
// ---------------------------------------------------------------------------

$dbPath = $root . '/config/database.php';
if (!is_readable($dbPath)) {
    if ($productionMode) {
        cc_check_fail('config/database.php is missing (copy from database.example.php on the host)');
    } else {
        cc_check_warn('config/database.php is missing locally (copy from database.example.php to develop)');
    }
} else {
    /** @var array<string, mixed> $db */
    $db = require $dbPath;
    cc_check_ok('config/database.php is present (contents are never printed)');

    $required = ['host', 'dbname', 'username', 'password', 'charset'];
    $missing = [];
    foreach ($required as $key) {
        if (!array_key_exists($key, $db)) {
            $missing[] = $key;
        }
    }
    if ($missing === []) {
        cc_check_ok('database.php defines host, dbname, username, password, charset');
    } else {
        cc_check_fail('database.php is missing keys: ' . implode(', ', $missing));
    }

    $dbname = isset($db['dbname']) ? (string) $db['dbname'] : '';
    $username = isset($db['username']) ? (string) $db['username'] : '';
    $password = isset($db['password']) ? (string) $db['password'] : '';
    $charset = isset($db['charset']) ? (string) $db['charset'] : '';

    if (str_starts_with($dbname, 'your_') || str_starts_with($username, 'your_') || str_starts_with($password, 'your_')) {
        if ($productionMode) {
            cc_check_fail('database.php still contains example placeholders (replace with host credentials)');
        } else {
            cc_check_warn('database.php still contains example placeholders');
        }
    } else {
        cc_check_ok('database.php no longer uses the example placeholder values');
    }

    if ($charset === 'utf8mb4') {
        cc_check_ok('database.php charset is utf8mb4');
    } else {
        cc_check_fail('database.php charset should be utf8mb4');
    }

    // Length-only checks so we never echo secrets.
    if (strlen($password) >= 8) {
        cc_check_ok('database.php password length is at least 8 characters');
    } elseif ($password === '') {
        if ($productionMode) {
            cc_check_fail('database.php password is empty');
        } else {
            cc_check_warn('database.php password is empty');
        }
    } else {
        cc_check_warn('database.php password is shorter than 8 characters');
    }
}

// ---------------------------------------------------------------------------
// 5. Summary (still credential-free)
// ---------------------------------------------------------------------------

echo str_repeat('-', 52) . PHP_EOL;
echo "Passed: {$pass}  Failed: {$fail}  Warnings: {$warn}" . PHP_EOL;

if ($fail > 0) {
    echo "RESULT: FAIL (fix the items above before deploying)" . PHP_EOL;
    exit(1);
}

echo "RESULT: PASS (repository templates safe; runtime config ready for this mode)" . PHP_EOL;
exit(0);
