<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Build Performance Chart Data API.
// Accepts selected component IDs and returns gaming, productivity, and upgrade-ceiling scores for
// the builder performance chart. Scores are computed server-side from database attributes only.
// Endpoint: POST api/chart-data.php Content-Type: application/json
// Request body: { "components": [1, 8, 15, 24, 28] }
// Response (200): { "success": true, "chart": { "labels": ["Gaming", "Productivity", "Upgrade
// headroom"], "datasets": [ { "label": "This build", "data": [82, 74, 18] }, { "label": "Catalogue
// ceiling", "data": [100, 96, 0] } ] }, "scores": { "gaming": 82, "productivity": 74... },
// "fallback": [ { "label": "...", "value": "..." }... ] }
// Access: None (public). Used by the guest-accessible builder.
// Security:
//   POST only.
//   Integer ID validation, max 20 components.
//   Only active components contribute.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/performance.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request body is empty.']);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload) || !isset($payload['components'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON. Expected {"components": [...]}.']);
    exit;
}

$componentIds = $payload['components'];

if (!is_array($componentIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '"components" must be an array of integers.']);
    exit;
}

$maxComponents = 20;
$cleanIds = [];

foreach ($componentIds as $id) {
    if (is_int($id) && $id > 0) {
        $cleanIds[] = $id;
    } elseif (is_string($id) && ctype_digit($id) && (int) $id > 0) {
        $cleanIds[] = (int) $id;
    }
}

$cleanIds = array_values(array_unique($cleanIds));

if (count($cleanIds) > $maxComponents) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Too many components. Maximum is ' . $maxComponents . '.']);
    exit;
}

try {
    $pdo = customcore_pdo();
    $rows = customcore_performance_load_components($pdo, $cleanIds);
    $report = customcore_performance_report($pdo, $rows);

    $gaming = (int) $report['gaming'];
    $productivity = (int) $report['productivity'];
    $upgradeGaming = (int) $report['upgrade_gaming'];
    $upgradeProductivity = (int) $report['upgrade_productivity'];
    $headroom = (int) $report['upgrade_headroom'];

    echo json_encode([
        'success' => true,
        'scores' => [
            'gaming' => $gaming,
            'productivity' => $productivity,
            'upgrade_gaming' => $upgradeGaming,
            'upgrade_productivity' => $upgradeProductivity,
            'upgrade_headroom' => $headroom,
        ],
        'chart' => [
            'labels' => ['Gaming', 'Productivity', 'Upgrade headroom'],
            'datasets' => [
                [
                    'label' => 'This build',
                    'data' => [$gaming, $productivity, $headroom],
                ],
                [
                    'label' => 'Catalogue ceiling',
                    'data' => [$upgradeGaming, $upgradeProductivity, 0],
                ],
            ],
        ],
        'by_category' => $report['by_category'],
        'fallback' => [
            [
                'label' => 'Gaming performance',
                'value' => $gaming > 0 ? $gaming . ' / 100' : 'Not enough scored parts yet',
            ],
            [
                'label' => 'Productivity performance',
                'value' => $productivity > 0 ? $productivity . ' / 100' : 'Not enough scored parts yet',
            ],
            [
                'label' => 'Catalogue gaming ceiling',
                'value' => $upgradeGaming . ' / 100',
            ],
            [
                'label' => 'Catalogue productivity ceiling',
                'value' => $upgradeProductivity . ' / 100',
            ],
            [
                'label' => 'Upgrade headroom',
                'value' => $headroom . ' points remaining on average',
            ],
        ],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    $msg = customcore_is_debug() ? $exception->getMessage() : 'Failed to compute chart data.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
