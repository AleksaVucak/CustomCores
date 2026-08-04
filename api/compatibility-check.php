<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Server-Side Compatibility Checker API.
// Accepts a JSON payload of component IDs and returns compatibility results via the shared
// evaluator in includes/compatibility.php.
// Endpoint: POST api/compatibility-check.php Content-Type: application/json
// Request body: { "components": [1, 8, 15, 24, 28, 35, 40, 48, 54, 57] }
// Response (200): { "success": true, "status": "compatible" | "warning" | "incompatible",
// "results": [ { "rule_code", "name", "status", "severity", "message" }... ] }
// Access: None (public). The builder is available to guests.
// Security:
//   POST only.
//   Input validated: array of integers, max 20.
//   Only active components and rules are used.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/compatibility.php';

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

if (count($cleanIds) === 0) {
    echo json_encode([
        'success' => true,
        'status' => 'compatible',
        'results' => [],
    ]);
    exit;
}

if (count($cleanIds) > $maxComponents) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Too many components. Maximum is ' . $maxComponents . '.']);
    exit;
}

try {
    $pdo = customcore_pdo();
    $report = customcore_compatibility_check($pdo, $cleanIds);

    echo json_encode([
        'success' => true,
        'status' => $report['status'],
        'results' => $report['results'],
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    $msg = customcore_is_debug() ? $exception->getMessage() : 'Compatibility check failed.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
