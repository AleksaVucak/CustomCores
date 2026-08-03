<?php
/**
 * CustomCore — Application health checks (Commit 13.1).
 *
 * File responsibility:
 *   Provides the monitoring "engine": a set of independent health checks that
 *   each return a CONTROLLED result (a status plus a safe, human-readable
 *   summary) for a core part of the site — the PHP runtime, the database,
 *   sessions, critical files, upload directories, the active theme, and the
 *   Learning Centre media. The Stage 13.2 administrator dashboard renders these
 *   results; Stage 13.3 adds live statistics on top.
 *
 * Design rules:
 *   - Every check is wrapped so it can NEVER throw. A failing dependency must
 *     downgrade its own status, not take down the monitoring page.
 *   - Messages are safe for production: no passwords, DSNs, absolute paths, or
 *     stack traces are exposed (Stage 13.4 hardens this further). Database
 *     errors reuse customcore_database_error_message(), which already respects
 *     debug mode and scrubs credentials.
 *   - Checks read real state (files on disk, PDO connection, config), never
 *     decorative hard-coded values.
 *
 * Status vocabulary:
 *   'online'  — the service is fully operational.
 *   'warning' — degraded or a non-critical dependency is missing; the core site
 *               still works (e.g. uploads not writable, a media file missing).
 *   'offline' — a critical dependency is unavailable (e.g. database down).
 *
 * Usage:
 *   require_once __DIR__ . '/monitoring.php';
 *   $report = customcore_monitoring_run();
 *   // $report['overall'], $report['generated_at'], $report['checks'][...]
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Severity ranking for a status (higher is worse). Unknown values rank as OK.
 */
function customcore_monitoring_status_rank(string $status): int
{
    switch ($status) {
        case 'offline':
            return 2;
        case 'warning':
            return 1;
        case 'online':
        default:
            return 0;
    }
}

/**
 * Human-readable label for a status, for dashboards and documentation.
 */
function customcore_monitoring_status_label(string $status): string
{
    switch ($status) {
        case 'offline':
            return 'Offline';
        case 'warning':
            return 'Warning';
        case 'online':
            return 'Online';
        default:
            return 'Unknown';
    }
}

/**
 * CSS badge modifier class for a status, reusing the shared admin badge styles.
 *
 * UI helper kept alongside the other status helpers, mirroring how order and
 * consultation status classes live with their domain logic.
 */
function customcore_monitoring_status_badge_class(string $status): string
{
    switch ($status) {
        case 'offline':
            return 'admin-badge--danger';
        case 'warning':
            return 'admin-badge--warn';
        case 'online':
            return 'admin-badge--ok';
        default:
            return 'admin-badge--muted';
    }
}

/**
 * Combine several statuses into the single worst (most severe) one.
 *
 * @param list<string> $statuses
 */
function customcore_monitoring_worst(array $statuses): string
{
    $worst = 'online';
    foreach ($statuses as $status) {
        if (customcore_monitoring_status_rank($status) > customcore_monitoring_status_rank($worst)) {
            $worst = $status;
        }
    }

    return $worst;
}

/**
 * Build a single, uniform check result.
 *
 * @param list<string> $details Optional short, safe supporting lines.
 * @return array{key:string, label:string, status:string, summary:string, details:list<string>}
 */
function customcore_monitoring_result(
    string $key,
    string $label,
    string $status,
    string $summary,
    array $details = []
): array {
    return [
        'key' => $key,
        'label' => $label,
        'status' => $status,
        'summary' => $summary,
        'details' => array_values(array_map('strval', $details)),
    ];
}

/**
 * Absolute project root (the directory that contains includes/, config/, ...).
 */
function customcore_monitoring_root(): string
{
    return dirname(__DIR__);
}

/**
 * PHP runtime check: version and the extensions the application depends on.
 */
function customcore_monitoring_check_php(): array
{
    try {
        $details = ['PHP version: ' . PHP_VERSION];
        $status = 'online';
        $summary = 'PHP runtime and required extensions are present.';

        if (PHP_VERSION_ID < 80000) {
            $status = 'offline';
            $summary = 'PHP 8.0 or newer is required.';
        }

        // Essential: PDO + the MySQL driver. Without these the site cannot run.
        $hasPdo = extension_loaded('pdo') && extension_loaded('pdo_mysql');
        $details[] = 'PDO MySQL driver: ' . ($hasPdo ? 'loaded' : 'missing');
        if (!$hasPdo) {
            $status = customcore_monitoring_worst([$status, 'offline']);
            $summary = 'The PDO MySQL extension is not available.';
        }

        // Recommended: fileinfo powers real-MIME validation on uploads.
        $hasFinfo = extension_loaded('fileinfo');
        $details[] = 'fileinfo extension: ' . ($hasFinfo ? 'loaded' : 'missing');
        if (!$hasFinfo) {
            $status = customcore_monitoring_worst([$status, 'warning']);
            if ($status !== 'offline') {
                $summary = 'fileinfo is missing; secure file uploads are disabled.';
            }
        }

        // Recommended: session support underpins auth, cart, and flash.
        $hasSession = extension_loaded('session');
        $details[] = 'session extension: ' . ($hasSession ? 'loaded' : 'missing');
        if (!$hasSession) {
            $status = customcore_monitoring_worst([$status, 'offline']);
            $summary = 'The session extension is not available.';
        }

        return customcore_monitoring_result('php', 'PHP runtime', $status, $summary, $details);
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'php',
            'PHP runtime',
            'warning',
            'PHP runtime status could not be fully determined.'
        );
    }
}

/**
 * Database check: open the shared PDO connection and run a trivial query.
 */
function customcore_monitoring_check_database(): array
{
    try {
        require_once __DIR__ . '/database.php';
        $pdo = customcore_pdo();
        $stmt = $pdo->query('SELECT 1');
        $ok = $stmt !== false && (int) $stmt->fetchColumn() === 1;

        if (!$ok) {
            return customcore_monitoring_result(
                'database',
                'Database (MySQL)',
                'offline',
                'The database connection opened but the test query failed.'
            );
        }

        return customcore_monitoring_result(
            'database',
            'Database (MySQL)',
            'online',
            'Connected and responding to queries.'
        );
    } catch (Throwable $exception) {
        // customcore_database_error_message() is already production-safe and
        // scrubs credentials; fall back to a generic line if it is unavailable.
        $message = function_exists('customcore_database_error_message')
            ? customcore_database_error_message($exception)
            : 'The database is temporarily unavailable.';

        return customcore_monitoring_result(
            'database',
            'Database (MySQL)',
            'offline',
            $message
        );
    }
}

/**
 * Session check: confirm session support is usable and its store is writable.
 *
 * Never prints the session save path (avoids filesystem disclosure).
 */
function customcore_monitoring_check_sessions(): array
{
    try {
        $state = session_status();

        if ($state === PHP_SESSION_DISABLED) {
            return customcore_monitoring_result(
                'sessions',
                'Sessions',
                'offline',
                'PHP sessions are disabled on this server.'
            );
        }

        $details = [];
        $details[] = $state === PHP_SESSION_ACTIVE
            ? 'A session is currently active.'
            : 'Session support is available.';

        // If a custom save path is configured, confirm it is writable without
        // revealing the path itself.
        $savePath = (string) session_save_path();
        if ($savePath !== '') {
            // Handle "N;/path" and "N;mode;/path" formats by taking the last segment.
            $segments = explode(';', $savePath);
            $candidate = trim((string) end($segments));
            if ($candidate !== '' && !is_writable($candidate)) {
                return customcore_monitoring_result(
                    'sessions',
                    'Sessions',
                    'warning',
                    'The session storage location is not writable; logins may not persist.',
                    $details
                );
            }
            $details[] = 'Session storage is writable.';
        } else {
            $details[] = 'Using the server default session store.';
        }

        return customcore_monitoring_result(
            'sessions',
            'Sessions',
            'online',
            'Session handling is operational.',
            $details
        );
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'sessions',
            'Sessions',
            'warning',
            'Session status could not be fully determined.'
        );
    }
}

/**
 * Critical files check: confirm core includes, config, and base assets exist.
 *
 * Only relative project paths are ever reported (never absolute filesystem
 * paths), so the output is safe to show to an administrator.
 */
function customcore_monitoring_check_files(): array
{
    try {
        $root = customcore_monitoring_root();

        // Missing any of these breaks the core application.
        $required = [
            'config/app.php',
            'config/database.php',
            'includes/functions.php',
            'includes/database.php',
            'includes/header.php',
            'includes/footer.php',
            'includes/navigation.php',
            'includes/theme.php',
            'includes/flash.php',
            'includes/auth.php',
            'assets/css/main.css',
            'assets/js/main.js',
        ];

        // Helpful but not fatal for public browsing.
        $recommended = [
            'assets/css/admin.css',
            'includes/admin.php',
            'includes/admin-auth.php',
        ];

        $missingRequired = [];
        foreach ($required as $relative) {
            if (!is_file($root . '/' . $relative)) {
                $missingRequired[] = $relative;
            }
        }

        $missingRecommended = [];
        foreach ($recommended as $relative) {
            if (!is_file($root . '/' . $relative)) {
                $missingRecommended[] = $relative;
            }
        }

        if ($missingRequired !== []) {
            return customcore_monitoring_result(
                'files',
                'Core files',
                'offline',
                count($missingRequired) . ' critical file(s) are missing.',
                array_map(static fn (string $p): string => 'Missing: ' . $p, $missingRequired)
            );
        }

        if ($missingRecommended !== []) {
            return customcore_monitoring_result(
                'files',
                'Core files',
                'warning',
                count($missingRecommended) . ' recommended file(s) are missing.',
                array_map(static fn (string $p): string => 'Missing: ' . $p, $missingRecommended)
            );
        }

        return customcore_monitoring_result(
            'files',
            'Core files',
            'online',
            'All critical application files are present.',
            [count($required) . ' required files verified.']
        );
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'files',
            'Core files',
            'warning',
            'Core file status could not be fully determined.'
        );
    }
}

/**
 * Upload directories check: confirm they exist and are writable.
 *
 * A missing or read-only upload directory degrades a feature (product image
 * uploads, consultation attachments) but does not break the core site, so it is
 * reported as a warning rather than offline.
 */
function customcore_monitoring_check_uploads(): array
{
    try {
        $root = customcore_monitoring_root();
        $app = customcore_app_config();
        $paths = isset($app['paths']) && is_array($app['paths']) ? $app['paths'] : [];

        $targets = [
            'Product images' => (string) ($paths['uploads_products'] ?? 'uploads/products'),
            'Consultation attachments' => (string) ($paths['uploads_consultation'] ?? 'uploads/consultation'),
        ];

        $details = [];
        $status = 'online';

        foreach ($targets as $label => $relative) {
            $full = $root . '/' . ltrim($relative, '/');
            if (!is_dir($full)) {
                $status = customcore_monitoring_worst([$status, 'warning']);
                $details[] = $label . ': directory missing (' . $relative . ').';
                continue;
            }
            if (!is_writable($full)) {
                $status = customcore_monitoring_worst([$status, 'warning']);
                $details[] = $label . ': not writable (' . $relative . ').';
                continue;
            }
            $details[] = $label . ': writable.';
        }

        $summary = $status === 'online'
            ? 'Upload directories exist and are writable.'
            : 'One or more upload directories need attention.';

        return customcore_monitoring_result('uploads', 'Upload storage', $status, $summary, $details);
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'uploads',
            'Upload storage',
            'warning',
            'Upload directory status could not be fully determined.'
        );
    }
}

/**
 * Theme check: confirm the active theme stylesheet resolves to a real file.
 *
 * The base main.css is always linked, so even a missing theme leaves the site
 * styled; that case is a warning, not an outage.
 */
function customcore_monitoring_check_themes(): array
{
    try {
        require_once __DIR__ . '/theme.php';

        $available = function_exists('customcore_theme_scan_available')
            ? customcore_theme_scan_available()
            : [];
        $active = function_exists('customcore_active_theme_file')
            ? customcore_active_theme_file()
            : null;

        $details = [count($available) . ' theme stylesheet(s) available.'];

        if ($active === null) {
            return customcore_monitoring_result(
                'themes',
                'Site theme',
                'warning',
                'No theme stylesheet resolved; the site falls back to base styles only.',
                $details
            );
        }

        $details[] = 'Active theme: ' . basename($active);

        return customcore_monitoring_result(
            'themes',
            'Site theme',
            'online',
            'The active theme stylesheet is present and valid.',
            $details
        );
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'themes',
            'Site theme',
            'warning',
            'Theme status could not be fully determined.'
        );
    }
}

/**
 * Media check: compare the declared Learning Centre catalogue against disk.
 *
 * Detects a missing primary media file, poster, or caption track. Missing media
 * degrades the Learning Centre but not the core site, so it is a warning.
 */
function customcore_monitoring_check_media(): array
{
    try {
        require_once __DIR__ . '/media.php';

        $declared = function_exists('customcore_media_catalogue')
            ? customcore_media_catalogue()
            : [];
        $total = count($declared);

        if ($total === 0) {
            return customcore_monitoring_result(
                'media',
                'Learning Centre media',
                'warning',
                'No Learning Centre media lessons are declared.'
            );
        }

        $missingPrimary = 0;
        $missingSupporting = 0;
        $details = [];

        foreach ($declared as $item) {
            $title = isset($item['title']) ? (string) $item['title'] : (string) ($item['id'] ?? 'lesson');
            $src = isset($item['src']) ? (string) $item['src'] : '';

            if ($src === '' || customcore_media_url($src) === null) {
                $missingPrimary++;
                $details[] = 'Missing media file: ' . $title;
                continue;
            }

            $poster = isset($item['poster']) ? (string) $item['poster'] : '';
            if ($poster !== '' && customcore_image_url($poster) === null) {
                $missingSupporting++;
                $details[] = 'Missing poster image: ' . $title;
            }

            $captions = isset($item['captions']) ? (string) $item['captions'] : '';
            if ($captions !== '' && customcore_media_url($captions) === null) {
                $missingSupporting++;
                $details[] = 'Missing captions: ' . $title;
            }
        }

        $available = $total - $missingPrimary;

        if ($missingPrimary > 0) {
            return customcore_monitoring_result(
                'media',
                'Learning Centre media',
                'warning',
                $missingPrimary . ' of ' . $total . ' media lesson file(s) are missing.',
                $details
            );
        }

        if ($missingSupporting > 0) {
            return customcore_monitoring_result(
                'media',
                'Learning Centre media',
                'warning',
                $missingSupporting . ' supporting media asset(s) (posters/captions) are missing.',
                $details
            );
        }

        return customcore_monitoring_result(
            'media',
            'Learning Centre media',
            'online',
            'All ' . $available . ' media lesson(s) and their assets are present.'
        );
    } catch (Throwable $exception) {
        return customcore_monitoring_result(
            'media',
            'Learning Centre media',
            'warning',
            'Media status could not be fully determined.'
        );
    }
}

/**
 * Run every health check and return a single report.
 *
 * The report is resilient: each check is self-contained and cannot throw, so an
 * individual failure downgrades only its own row while the rest still report.
 *
 * @return array{
 *   generated_at:string,
 *   overall:string,
 *   checks:list<array{key:string, label:string, status:string, summary:string, details:list<string>}>
 * }
 */
function customcore_monitoring_run(): array
{
    $checks = [
        customcore_monitoring_check_php(),
        customcore_monitoring_check_database(),
        customcore_monitoring_check_sessions(),
        customcore_monitoring_check_files(),
        customcore_monitoring_check_uploads(),
        customcore_monitoring_check_themes(),
        customcore_monitoring_check_media(),
    ];

    $statuses = array_map(
        static fn (array $check): string => (string) $check['status'],
        $checks
    );

    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'overall' => customcore_monitoring_worst($statuses),
        'checks' => $checks,
    ];
}
